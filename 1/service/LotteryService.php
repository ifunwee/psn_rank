<?php

class LotteryService extends BaseService
{
    public function getLotteryListByCurrent()
    {
        $where['status'] = 1;
        $field = array('id', 'prize_title', 'prize_image', 'lottery_time');
        $list = $this->getLotteryListFromDb($where, $field, 'lottery_time asc', 1);

        $data['list'] = $list;
        return $data;
    }

    public function getLotteryListByHistory($page)
    {
        $where['status'] = 2;
        $field = array('id', 'prize_title', 'prize_image', 'lottery_time');
        $list = $this->getLotteryListFromDb($where, $field, 'lottery_time desc', $page);

        $data['list'] = $list;
        return $data;
    }

    public function getLotteryListByMyJoin()
    {

    }

    public function getLotteryDetail($lottery_id, $user_id)
    {
        $lottery_info = $this->getLotteryInfo($lottery_id);

        $data = array(
            "prize_title" => $lottery_info['prize_title'] ? : '',
            "prize_image" => $lottery_info['prize_image'] ? : '',
            "prize_description" => $lottery_info['prize_description'] ? : '',
            "start_time" => $lottery_info['start_time'] ? : 0,
            "end_time" => $lottery_info['end_time'] ? : 0,
            "status" => $lottery_info['status'] ? : 0,
            "lottery_time" => $lottery_info['lottery_time'] ? : 0,
            "lottery_num" => $lottery_info['lottery_num'] ? : 0,
            "prize_winner" => $lottery_info['prize_winner'] ? array_values(json_decode($lottery_info['prize_winner'], true)) : array(),
            "is_win" => $data['is_win'] = $this->isWinPrize($lottery_id, $user_id),
        );

        return $data;
    }

    public function getLotteryTicket($user_id, $lottery_id)
    {
        if (empty($lottery_id)) {
            return $this->setError('param_lottery_id_empty', '缺少参数');
        }

        if (empty($user_id)) {
            return $this->setError('param_user_id_empty', '缺少参数');
        }

        $redis = r('psn_redis');
        $interval_limit_key = redis_key('lottery_interval_limit', $user_id);
        $allow = $redis->setnx($interval_limit_key, time());
        $redis->expire($interval_limit_key, 10);
        if (!$allow) {
            return $this->setError('lottery_interval_limit', '您实在是太热情了，休息一会吧');
        }

        $day_limit_key = redis_key('lottery_day_limit', date('Ymd'), $user_id, $lottery_id);
        $num = $redis->get($day_limit_key);
        if ($num >= 10) {
            return $this->setError('lottery_day_limit', '当日获得的小手柄已达上限，明日再来喔');
        }

        $lottery_info = $this->getLotteryInfo($lottery_id);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $now = time();
        if ($now < $lottery_info['start_time'] || $now > $lottery_info['end_time']) {
            return $this->setError('lottery_time_not_allow', '抽奖活动未开始或已结束');
        }

        $lottery_ticket = $this->generateLotteryTicket($lottery_id);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $this->saveLotteryTicket($user_id, $lottery_id, $lottery_ticket);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $redis->incr($day_limit_key);

        $data['lottery_ticket'] = $lottery_ticket;
        return $data;
    }

    public function generateLotteryTicket($lottery_id)
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('lottery_ticket_publish', $lottery_id);
        $i = 1;

        while ($i <= 1000) {
            $ticket = mt_rand(10000001, 99999999);
            $is_member = $redis->sIsMember($redis_key, $ticket);
            if (!empty($is_member)) {
                $i++;
            } else {
                $redis->sAdd($redis_key, $ticket);
                return $ticket;
            }

            if ($i >= 1000) {
                return $this->setError('generate_ticket_fail_1000', '系统异常, 请稍后再试');
            }
        }
    }

    public function saveLotteryTicket($user_id, $lottery_id, $lottery_ticket)
    {
        $db = pdo();
        $db->tableName = 'lottery_ticket';
        $data = array(
            'user_id' => $user_id,
            'lottery_id' => $lottery_id,
            'lottery_ticket' => $lottery_ticket,
            'create_time' => time(),
        );

        try {
            $db->insert($data);
        } catch (Exception $e) {
            log::e($e->getMessage());
            return $this->setError('db_error', '系统异常，稍后再试');
        }
    }

    public function getLotteryInfo($lottery_id)
    {
        if (empty($lottery_id)) {
            return $this->setError('param_lottery_id_empty');
        }

        $info = $this->getLotteryInfoFromDb($lottery_id);
        if (empty($info) || $info['status'] == 0) {
            return $this->setError('lottery_is_no_exist');
        }

        return $info;
    }

    public function getLotteryInfoFromDb($lottery_id)
    {
        $db = pdo();
        $db->tableName = 'lottery';
        $where['id'] = $lottery_id;
        $info = $db->find($where);

        if (!empty($info)) {
            unset($info['create_time']);
            unset($info['update_time']);
        }

        return $info;
    }

    public function getLotteryListFromDb($where = '', $field = array(), $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? implode(',', $field) : '*';
        $sort = $sort ? $sort : 'id desc';
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'lottery';
        $list = $db->findAll($where, $field, $sort, $limit_str);

        return $list;
    }

    public function isWinPrize($lottery_id, $user_id)
    {
        $db = pdo();
        $db->tableName = 'lottery_ticket';
        $where['lottery_id'] = $lottery_id;
        $where['user_id'] = $user_id;
        $where['is_win'] = 1;
        $info = $db->find($where);

        return $info['lottery_ticket'] ? 1 : 0;
    }
}
