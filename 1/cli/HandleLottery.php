<?php
/**
 * Created by PhpStorm.
 * User: funwee
 * Date: 2019/9/25
 * Time: 5:34 PM
 */
class HandleLottery
{
    public function run()
    {
        $redis = r('psn_redis');
        $db = pdo();
        $now = time();
        $sql = "select * from lottery where {$now} >= lottery_time and status = 2";
        $list = $db->query($sql);

        if (empty($list)) {
            echo "没有需要开奖的抽奖活动 \r\n";
            return false;
        }


        $user_service = s('User');
        foreach ($list as $info) {
            $page = 1;
            $limit = 1000;

            if (empty($info['lottery_num'])) {
                echo "抽奖配置的奖品数量为空\r\n";
                return false;
            }

            if (!empty($info['prize_winner'])) {
                echo "状态为待开奖，但中奖名单却有数据，无法重复开奖，请排查异常\r\n";
                return false;
            }
            $lottery_ticket_pool_key = redis_key('lottery_ticket_pool', $info['id']);
            $lottery_blacklist = c('lottery_blacklist') ? : array();

            $db->tableName = 'lottery_ticket';
            //field涉及后续操作 顺序不可随意调整
            $field = 'lottery_ticket,user_id';
            $where = "lottery_id = {$info['id']} and status = 1";

            //过滤黑名单用户
            if (!empty($lottery_blacklist)) {
                $blacklist_str = implode("','", $lottery_blacklist);
                $where .= " and user_id not in ('$blacklist_str')";
            }

            while (true) {
                $start = ($page-1) * $limit;
                $limit_str = "{$start},{$limit}";
                $sql = "select {$field} from lottery_ticket where {$where} limit {$limit_str}";
                $lottery_ticket_list = $db->query($sql);
                if (empty($lottery_ticket_list)) {
                    break;
                }

                shuffle($lottery_ticket_list);
                foreach ($lottery_ticket_list as $value) {
                    if (empty($value['user_id']) || empty($value['lottery_ticket'])) {
                        echo "票据数据有误" . json_encode($value) . "\r\n";
                        continue;
                    }
                    $ticket_str = implode('_', $value);
                    $redis->sAdd($lottery_ticket_pool_key, $ticket_str);
                }

                $page++;
            }
            //意外情况保留三天数据 正常开奖则开奖后就删除缓存
            $redis->expire($lottery_ticket_pool_key, time() + 86400 * 3);

            $count = $redis->sCard($lottery_ticket_pool_key);
            echo "id:{$info['id']} count:$count \r\n";
            if ($count < 1) {
                log::e("推入奖池的奖券为空");
                echo "出现异常：推入奖池的奖券为空 \r\n";
                continue;
            }

            try {
                $sql = "select count(DISTINCT user_id) as lottery_user from lottery_ticket where {$where}";
                $result = $db->query($sql);
                $lottery_user = $result[0]['lottery_user'] ? : 0;
                if ($lottery_user < $info['lottery_num']) {
                    echo "活动设定的奖品数量大于有效抽奖人数 {$info['lottery_num']} {$lottery_user}\r\n";
                    $info['lottery_num'] = $lottery_user;
                }

                $winner_by_ticket = array();
                $winner = array();
                $prize_lottery_ticket = array();


                while ($count = $redis->sCard($lottery_ticket_pool_key)) {
                    $lottery_ticket_str = $redis->sPop($lottery_ticket_pool_key);
                    if (empty($lottery_ticket_str)) {
                        echo "开奖抽出的抽奖券为空 \r\n";
                        continue;
                    }

                    echo "lottery_ticket_str : $lottery_ticket_str \r\n";
                    $lottery_ticket_arr = explode('_', $lottery_ticket_str);
                    $lottery_ticket_info['lottery_ticket'] = $lottery_ticket_arr[0];
                    $lottery_ticket_info['user_id'] = $lottery_ticket_arr[1];
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

                if (empty($winner)) {
                    log::e("开奖结果的用户为空 超出脚本预期 请排查错误");
                    echo "开奖结果抽取的票券用户为空\r\n";
                    continue;
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
                    'status' => 3,
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

                $datetime = date('Y-m-d H:i:s');
                echo "{$datetime} 活动id:{$info['id']} 开奖成功 开奖结果：{$lottery_result} 中奖名单：{$prize_winner} \r\n";
            } catch (Exception $e) {
                echo "操作数据库出现异常：" . $e->getMessage() . "\r\n";
                log::e("db_error:" . $e->getMessage());
            }

            $redis->expire($lottery_ticket_pool_key, 0);

        }
    }

    public function end()
    {
        $now = time();
        $db = pdo();
        $db->tableName = 'lottery';
        $sql = "select * from lottery where {$now} >= end_time and status = 1";
        $list = $db->query($sql);

        if (empty($list)) {
            echo "没有结束抽奖的活动 \r\n";
            return false;
        }

        foreach ($list as $info) {
            try {
                $data['status'] = 2;
                $db->update($data, array('id' => $info['id']));
            } catch (Exception $e) {
                log::e("db_error:" . $e->getMessage());
                continue;
            }
            echo "抽奖活动{$info['id']}已标记结束\r\n";
        }
    }

    public function notice()
    {
        $db = pdo();
        $db->tableName = 'lottery';
        $where['is_notice'] = 0;
        $where['status'] = 3;
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

        $sql = "select * from 
                (select user_id,open_id,appcode from open_id where appcode = 2) a 
                RIGHT JOIN 
                (select user_id from lottery_ticket where lottery_id = 5 GROUP BY user_id) b 
                ON a.user_id = b.user_id;";

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

            $service = s('MiniProgram', 2);
            $open_id = $value['open_id'];
            $content['touser'] = $open_id;
            $content['template_id'] = 'SVLbOGNgPvtUPlTYN4fa_V__CouvNpIaVGDR1pxdnjc';
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
                    'value' => "{$info['prize_title']}",
                ),
                'keyword2' => array(
                    'value' => '您参与的抽奖活动已经开奖，点击查看',
                ),
            );
            $content['page'] = "pages/lotteryDetail/lotteryDetail?id={$info['id']}";

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