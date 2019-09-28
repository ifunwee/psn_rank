<?php

class LotteryService extends BaseService
{
    public function getLotteryListByCurrent($user_id = '')
    {
        $where['status'] = 1;
        $field = array('id', 'prize_title', 'prize_image', 'lottery_time');
        $list = $this->getLotteryListFromDb($where, $field, 'lottery_time asc', 1);

        if (empty($list)) {
            return $data['list'] = null;
        }

        foreach ($list as &$value) {
            $value['lottery_join_num'] = $this->getLotteryJoinNum($value['id']);

            if (!empty($user_id)) {
                $value['my_lottery_ticket_num'] = $this->getLotteryTicketNum($value['id'], $user_id);
            } else {
                $value['my_lottery_ticket_num'] = 0;
            }
        }

        unset($value);

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
        $redis = r('psn_redis');
        $lottery_info = $this->getLotteryInfo($lottery_id);
        $lottery_ticket_publish_key = redis_key('lottery_ticket_publish', $lottery_id);
        $lottery_ticket_rank_key = redis_key('lottery_ticket_rank', $lottery_id);

        $is_win = 0;
        $my_lottery_ticket_num = 0;
        $win_lottery_ticket = '';
        $rank = '-';
        $user_info = array();

        if (!empty($user_id)) {
            $win_lottery_ticket = $this->isWin($lottery_id, $user_id);
            $is_win = $win_lottery_ticket ? 1 : 0 ;
            $my_lottery_ticket_num = $this->getLotteryTicketNum($lottery_id, $user_id);
            $rank = $redis->zRevRank($lottery_ticket_rank_key, $user_id);

            $service = s('User');
            $user_info = $service->getUserInfoByUserId($user_id, array('nick_name', 'avatar_url'));
        }

        $data = array(
            'info' => array(
                "prize_title" => $lottery_info['prize_title'] ? : '',
                "prize_image" => $lottery_info['prize_image'] ? : '',
                "prize_description" => $lottery_info['prize_description'] ? : '',
                "start_time" => $lottery_info['start_time'] ? : 0,
                "end_time" => $lottery_info['end_time'] ? : 0,
                "status" => $lottery_info['status'] ? : 0,
                "lottery_time" => $lottery_info['lottery_time'] ? : 0,
                "lottery_num" => $lottery_info['lottery_num'] ? : 0,
                "lottery_ticket_publish" => $redis->scard($lottery_ticket_publish_key) ? : 0,
                "lottery_join_num" => $this->getLotteryJoinNum($lottery_id) ? : 0,
                "prize_winner" => $lottery_info['prize_winner'] ? array_values(json_decode($lottery_info['prize_winner'], true)) : array(),
            ),
            'my' => array(
                'nick_name' => $user_info['nick_name'] ? : '',
                'avatar_url' => $user_info['avatar_url'] ? : '',
                'is_win' => $is_win,
                'win_lottery_ticket' => $win_lottery_ticket ? : '',
                'rank' => is_numeric($rank) ? $rank + 1 : '-',
                'lottery_ticket_num' => $my_lottery_ticket_num ? : 0,
            ),
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
        $interval_lock_key = redis_key('lottery_interval_lock', $user_id);
        $allow = $redis->setnx($interval_lock_key, time());
        $redis->expire($interval_lock_key, 10);
        if (!$allow) {
            return $this->setError('lottery_interval_limit', '您实在是太热情了，休息一会吧');
        }

        $lottery_info = $this->getLotteryInfo($lottery_id);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $now = time();
        if ($now < $lottery_info['start_time'] || $now > $lottery_info['end_time']) {
            return $this->setError('lottery_time_not_allow', '抽奖活动未开始或已结束');
        }

        $day_limit_key = redis_key('lottery_day_join', date('Ymd'), $user_id, $lottery_id);
        $num = $redis->get($day_limit_key);
        if ($num >= 5) {
            return $this->setError('lottery_day_limit', '当日获得的小手柄已达上限，明日再来喔');
        }


        $lottery_ticket = $this->generateLotteryTicket($lottery_id, $lottery_info['lottery_time']);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $this->saveLotteryTicket($user_id, $lottery_id, $lottery_ticket);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $redis->incr($day_limit_key);

        //记录我参与的游戏
        $lottery_my_join_key = redis_key('lottery_my_join', $user_id);
        $exist = $redis->zScore($lottery_my_join_key, $user_id);
        if (empty($exist)) {
            $redis->zAdd('lottery_my_join', time(), $user_id);
        }

        $lottery_ticket_rank_key = redis_key('lottery_ticket_rank', $lottery_id);
        $score = $redis->zIncrBy($lottery_ticket_rank_key, 1, $user_id);
        //首次获取票券 加入时间因子 用于同名排序
        if ($score == 1) {
            $redis->zIncrBy($lottery_ticket_rank_key, time()/pow(10,10), $user_id);
        }

        $data['lottery_ticket'] = $lottery_ticket;
        $data['lottery_ticket_num'] = floor($score);
        return $data;
    }

    public function generateLotteryTicket($lottery_id, $lottery_time)
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
                $redis->expire($redis_key, $lottery_time + 86400);
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

    protected function getLotteryListFromDb($where = '', $field = array(), $sort = '', $page = 1, $limit = 20)
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

    public function isWin($lottery_id, $user_id)
    {
        $db = pdo();
        $db->tableName = 'lottery_ticket';
        $where['lottery_id'] = $lottery_id;
        $where['user_id'] = $user_id;
        $where['is_win'] = 1;
        $info = $db->find($where);

        return $info['lottery_ticket'] ? : 0;
    }

    public function getLotteryJoinNum($lottery_id)
    {
        $redis = r('psn_redis');
        $lottery_ticket_rank_key = redis_key('lottery_ticket_rank', $lottery_id);
        $num = $redis->zCard($lottery_ticket_rank_key);

        return $num ? : 0;
    }

    public function getLotteryTicketNum($lottery_id, $user_id)
    {
        $redis = r('psn_redis');
        $lottery_ticket_rank_key = redis_key('lottery_ticket_rank', $lottery_id);
        $score = $redis->zScore($lottery_ticket_rank_key, $user_id);
        $num = floor($score);

        return $num ? : 0;
    }

    public function getLotteryTicketNumFromDb($lottery_id, $user_id)
    {
        $db = pdo();
        $db->tableName = 'lottery_ticket';
        $where['lottery_id'] = $lottery_id;
        $where['user_id'] = $user_id;

        $num = $db->num($where);
        return $num ? : 0;
    }

    public function getLotteryTicketRank($lottery_id, $page = 1, $limit = 20)
    {
        $redis = r('psn_redis');
        $start = ($page - 1) * $limit;
        $end = $page * $limit - 1;
        $rank = $start + 1;
        $lottery_ticket_rank_key = redis_key('lottery_ticket_rank', $lottery_id);
        $rank_list = $redis->zRevRange($lottery_ticket_rank_key, $start, $end, true);
        if (empty($rank_list)) {
            return $data['list'] = null;
        }

        $user_id_arr = array_keys($rank_list);
        /** @var  $service UserService */
        $service = s('User');
        $user_info = $service->getUserInfoByUserId($user_id_arr, array('nick_name', 'avatar_url'));

        $list = array();
        foreach ($rank_list as $user_id => $num) {
            $list[] = array(
                'rank' => $rank,
                'nick_name' => $user_info[$user_id]['nick_name'],
                'avatar_url' => $user_info[$user_id]['avatar_url'],
                'num' => floor($num) ? : 0,
            );

            $rank++;
        }

        $data['list'] = $list;
        return $data;

    }

    public function getMyLotteryTicketList($lottery_id, $user_id, $page = 1)
    {

        $where['lottery_id'] = $lottery_id;
        $where['user_id'] = $user_id;
        $field = array('lottery_ticket', 'is_win', 'create_time');

        $list = $this->getUserLotteryTicketListFromDb($where, $field, 'is_win desc, id desc', $page);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $data['list'] = $list;
        return $data;
    }

    protected function getUserLotteryTicketListFromDb($where = '', $field = array(), $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? implode(',', $field) : '*';
        $sort = $sort ? $sort : 'id desc';
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'lottery_ticket';
        $list = $db->findAll($where, $field, $sort, $limit_str);

        return $list;
    }

    public function receivePrize($lottery_id, $user_id, $lottery_ticket, $name, $mobile, $address)
    {
        if (empty($lottery_id) || empty($user_id) || empty($lottery_ticket)) {
            return $this->setError('param_is_missing', '缺少参数');
        }

        if (empty($name)) {
            return $this->setError('param_name_empty', '请填写联系人');
        }

        if (empty($mobile)) {
            return $this->setError('param_mobile_empty', '请填写联系电话');
        }

        if (empty($address)) {
            return $this->setError('param_name_empty', '请填写联系地址');
        }

        $db = pdo();
        $db->tableName = 'lottery_ticket';
        $where['lottery_id'] = $lottery_id;
        $where['user_id'] = $user_id;
        $where['lottery_ticket'] = $lottery_ticket;
        $where['is_win'] = 1;

        $info = $db->find($where);

        if (empty($info)) {
            return $this->setError('lottery_user_is_not_match', '请不要冒名顶替哦~');
        }

        $db->startTrans();

        try {
            $data = array(
                'is_receive' => 1,
            );
            $db->update($data, $where);

            $db->tableName = 'lottery_contact';
            $condition['user_id'] = $user_id;
            $contact = $db->find($condition);

            $data = array(
                'user_id' => $user_id,
                'name' => $name,
                'mobile' => $mobile,
                'address' => $address,
            );
            if (empty($contact)) {
                $data['create_time'] = time();
                $db->insert($data);
            } else {
                $data['update_time'] = time();
                $db->update($data, $condition);
            }
        } catch (Exception $e) {
            $db->rollBackTrans();
            log::e('操作数据库出现错误：'. $e->getMessage());
            return $this->setError('db_error', '系统错误，请稍后再试');
        }

        $db->commitTrans();
    }

    public function getContactInfo($user_id)
    {
        $db = pdo();
        $db->tableName = 'lottery_contact';
        $where['user_id'] = $user_id;
        $info = $db->find($where);

        $data = array(
            'name' => $info['name'] ? : '',
            'mobile' => $info['mobile'] ? : '',
            'address' => $info['address'] ? : '',
        );

        return $data;
        
    }
}
