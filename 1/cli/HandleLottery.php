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

                $sql = "select count(*) as publish_num from lottery_ticket where lottery_id = {$info['id']}";
                $result = $db->query($sql);
                $publish_num = $result[0]['publish_num'] ? : 0;

                $sql = "select count(DISTINCT user_id) as join_num from lottery_ticket where lottery_id = {$info['id']}";
                $result = $db->query($sql);
                $join_num = $result[0]['join_num'] ? : 0;

                $lottery_result = implode(',', $prize_lottery_ticket);
                $prize_winner = json_encode($winner_by_ticket, 256);
                $db->tableName = 'lottery';
                $data = array(
                    'status' => 2,
                    'lottery_result' => $lottery_result,
                    'prize_winner' => $prize_winner,
                    'lottery_publish_num' => $publish_num,
                    'lottery_join_num' => $join_num,
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

    public function notice()
    {
        $db = pdo();
        $db->tableName = 'lottery';
        $where['is_notice'] = 0;
        $where['status'] = 2;
        $info = $db->find($where);

        if (empty($info)) {
            echo "没有需要开奖通知的抽奖活动";
            return false;
        }

        $redis = r('psn_redis');
        $redis_key = redis_key('lottery_notice_lock', $info['id']);
        $allow = $redis->setnx($redis_key, time());
        $redis->expire($redis_key, time() + 86400);

        if (!$allow) {
            echo "lottery:notice_lock:{$info['id']} \r\n";
            return false;
        }

        $data['is_notice'] = 1;
        $db->update($data, array('id' => $info['id']));

        $sql = "select a.user_id,a.open_id,a.appcode from user a RIGHT JOIN (select user_id from lottery_ticket where lottery_id = {$info['id']} GROUP BY user_id) b ON a.user_id = b.user_id";
        $list = $db->query($sql);

        if (empty($list)) {
            echo "没有要推送的参与用户";
            return false;
        }

        foreach ($list as $value) {
            if (!is_numeric($value['appcode'])) {
                echo "无法识别的appcode: {$value['appcode']}";
                continue;
            }

            $service = s('MiniProgram', $value['appcode']);
            $open_id = $value['open_id'];
            $content['touser'] = $open_id;
            $content['template_id'] = 'BOQSUtVluGMal68HJAh6XZlO2X7kxg8_V8WCo_VGCkA';
            $form_id = $service->getFormId($open_id);

            if ($service->hasError()) {
                echo ("get_form_id_fail: $open_id" . json_encode($service->getError()) . "\r\n");
                log::w("get_form_id_fail: $open_id " . json_encode($service->getError()));
                $service->flushError();
                continue;
            }
            $content['form_id'] = $form_id['form_id'];
            $content['data'] = array(
                'keyword1' => array(
                    'value' => "活动奖品：{$info['prize_title']}",
                ),
                'keyword2' => array(
                    'value' => '无',
                ),
                'keyword3' => array(
                    'value' => "您参与的抽奖活动已经开奖，点击查看" ,
                ),
            );
            $content['page'] = '';

            $json = json_encode($content);
            $service->sendMessage($json);
            if ($service->hasError()) {
                echo "send_message_fail: {$open_id} {$json} \r\n" .  json_encode($service->getError());
                log::w("send_message_fail:" . json_encode($service->getError()) . $json);
                $service->flushError();
                continue;
            }
            log::i("send_message_success: {$open_id} {$json}");
            echo "send_message_success: {$open_id} {$json} \r\n";
        }
    }
}