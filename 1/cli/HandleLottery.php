<?php
/**
 * Created by PhpStorm.
 * User: funwee
 * Date: 2019/9/25
 * Time: 5:34 PM
 */
class HandleLottery
{
    public function start()
    {
        $redis = r('psn_redis');
        $db = pdo();
        $now = time();
        $sql = "select * from lottery where {$now} >= lottery_time and status = 1";
        $list = $db->query($sql);

        if (empty($list)) {
            echo "没有需要开奖的抽奖活动 \r\n";
            return false;
        }

        $page = 1;
        $limit = 1000;
        $user_service = s('User');
        foreach ($list as $info) {
            if (empty($info['lottery_num'])) {
                return false;
            }
            $lottery_ticket_pool_key = redis_key('lottery_ticket_pool', $info['id']);

            $db->tableName = 'lottery_ticket';
            $where = array();
            $where['lottery_id'] = $info['id'];
            $where['status'] = 1;
            $field = 'lottery_ticket, user_id';

            while (true) {
                $start = ($page-1) * $limit;
                $limit_str = "{$start},{$limit}";

                $lottery_ticket_list = $db->findAll($where, $field, 'id desc', $limit_str);
                if (empty($lottery_ticket_list)) {
                    break;
                }

                shuffle($lottery_ticket_list);
                foreach ($lottery_ticket_list as $value) {
                    $redis->lpush($lottery_ticket_pool_key, json_encode($value, 256));
                }

                $page++;
            }

            try {
                $winner_by_ticket = array();
                $winner = array();
                $prize_lottery_ticket = array();

                while (($count = $redis->lLen($lottery_ticket_pool_key)) > 0) {
                    $rand = mt_rand(0, $count-1);
                    echo "id:{$info['id']} count:$count rand:$rand \r\n";
                    $lottery_ticket_json = $redis->lIndex($lottery_ticket_pool_key, $rand);
                    if (empty($lottery_ticket_json)) {
                        echo "开奖抽出的抽奖券为空 \r\n";
                        continue;
                    }

                    $lottery_ticket_info = json_decode($lottery_ticket_json, true);
                    echo "{$lottery_ticket_info['user_id']} \r\n";
                    if (in_array($lottery_ticket_info['user_id'], $winner)) {
                        continue;
                    }

                    $lottery_ticket_info['create_time'] = time();
                    $winner[] = $lottery_ticket_info['user_id'];
                    $winner_by_ticket[$lottery_ticket_info['lottery_ticket']] = $lottery_ticket_info;
                    $prize_lottery_ticket[] = $lottery_ticket_info['lottery_ticket'];
                    $winner_num = count($winner);
                    echo "winner_num:{$winner_num}  lottery_num:{$info['lottery_num']} \r\n";
                    if ($winner_num >= $info['lottery_num']) {
                        break;
                    }
                }

                $user_info = $user_service->getUserInfoByUserId($winner, array('nick_name', 'avatar_url'));
                foreach ($winner_by_ticket as &$value) {
                    $value['nickname'] = $user_info[$value['user_id']]['nick_name'];
                    $value['avatar_url'] = $user_info[$value['user_id']]['avatar_url'];
                }
                unset($value);

                $lottery_result = implode(',', $prize_lottery_ticket);
                $prize_winner = json_encode($winner_by_ticket, 256);
                $db->tableName = 'lottery';
                $data = array(
                    'status' => 2,
                    'lottery_result' => $lottery_result,
                    'prize_winner' => $prize_winner,
                );
                $condition = array();
                $condition['id'] = $info['id'];
                $db->update($data, $condition);

                $sql = "update lottery_ticket set is_win = 1 where lottery_id = {$info['id']} and lottery_ticket in ({$lottery_result})";
                $db->exec($sql);

                echo "活动id:{$info['id']} 开奖成功 开奖结果：{$lottery_result} 中奖名单：{$prize_winner} \r\n";
            } catch (Exception $e) {
                echo "操作数据库出现异常：" . $e->getMessage() . "\r\n";
                log::e("db_error:" . $e->getMessage());
            }

            $redis->expire($lottery_ticket_pool_key, 0);

        }
    }
}