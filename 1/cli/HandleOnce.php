<?php

class HandleOnce
{
    /**
     * 修复媒体资源前缀
     */
    public function fixMediaPrefix()
    {
        $db = pdo();
        $db->tableName = 'goods';
        $list = $db->findAll('1=1', '*', 'id asc');

        foreach ($list as $info) {
            if (!empty($info['preview'])) {
                $data['preview'] = preg_replace('/https:\/\/apollo2.dl.playstation.net/i','', $info['preview']) ;
            }

            if (!empty($info['screenshots'])) {
                $data['screenshots'] = preg_replace('/https:\/\/apollo2.dl.playstation.net/i','', $info['screenshots']) ;
            }

            if (!empty($info['cover_image'])) {
                $data['cover_image'] = str_replace('https://store.playstation.com', '', $info['cover_image']);
            }

            if (!empty($data)) {
                $db->update($data, array('goods_id' => $info['goods_id']));
                echo "数据校正成功 {$info['goods_id']} \r\n";
            }
        }
    }

    public function fixLowestPrice()
    {
        $db = pdo();
        $sql = 'update (select *,min(price) as min_price,min(`plus_price`) min_plus_price from `goods_price_history` group by goods_id) a, goods_price b set b.`lowest_price` = a.min_price, b.`plus_lowest_price` = a.min_plus_price where a.goods_id = b.goods_id';
        $db->exec($sql);
    }

    public function fixGameTrophyRelationToCache()
    {
        $db = pdo();
        $redis = r('psn_redis');
        $sql = "select game_id,np_communication_id from game where np_communication_id <> ''";
        $list = $db->query($sql);
        foreach ($list as $game) {
            $redis_key = redis_key('relation_game_trophy', $game['game_id']);
            $redis->set($redis_key, $game['np_communication_id']);
        }
    }

    public function warmTrophyData()
    {
        $db = pdo();
//        $sql = "select psn_id from user where psn_id <> ''";
//        $sql = "select psn_id from account where psn_id <> '' and create_time > 1563724800";
        $sql = "select id,psn_id from trophy_title_user where id < 24564 group by psn_id order by id desc limit 100";
        $list = $db->query($sql);

        $profile_service = s('Profile');
        $trophy_title_service = s('TrophyTitle');
        foreach ($list as $info) {
//            $profile_service->syncPsnInfo($info['psn_id']);
//            if ($profile_service->hasError()) {
//                echo "sync_psn_info_fail:{$profile_service->getErrorCode()} {$profile_service->getErrorMsg()} \r\n";
//                $profile_service->flushError();
//            }
            $trophy_title_service->syncUserTrophyTitle($info['psn_id'], 1);
            if ($trophy_title_service->hasError()) {
                echo "sync_trophy_title_fail:{$trophy_title_service->getErrorCode()} {$trophy_title_service->getErrorMsg()} \r\n";
                $trophy_title_service->flushError();
                continue;
            }

            echo "{$info['id']} {$info['psn_id']} 开始同步数据 \r\n";
            sleep(300);
        }

    }

    public function fixFollowListToCache()
    {
        $db = pdo();
        $db->tableName = 'follow';
        $page = 1;
        $limit = 1000;
        $redis = r('psn_redis');

        $is_loop = true;
        while ($is_loop) {
            $start = ($page - 1) * $limit;
            $limit_str = "{$start}, {$limit}";
            $list = $db->findAll('1=1', '*', 'id desc', $limit_str);

            if (empty($list)) {
                $is_loop = false;
            } else {
                foreach ($list as $info) {
                    $account_follow_key = redis_key('account_follow', $info['open_id']);
                    $goods_follow_key = redis_key('goods_follow', $info['goods_id']);
                    if ($info['goods_id'] == 'undefined' || $info['status'] == 0) {
                        $redis->zRem($account_follow_key, $info['goods_id']);
                        $redis->sRem($goods_follow_key, $info['open_id']);
                    } else {
                        $redis->zAdd($account_follow_key, $info['create_time'], $info['goods_id']);
                        $redis->sAdd($goods_follow_key, $info['open_id']);
                    }

                    echo "同步 {$info['open_id']} 关注 {$info['goods_id']} 成功，关注时间:{$info['create_time']} \r\n";
                }
                $page++;
            }
        }

        echo '脚本处理完成';
    }

    public function getJumpTicket()
    {
        $o = 30;
        $l = 5;
        while (true) {
            $service = s('Common');
            $url = "https://switch.vgjump.com/switch/lottery/getAllLotteryConfigListNewByPage?offset={$o}&limit={$l}";
            $response = $service->curl($url);

            if (empty($response)) {
                return false;
            }

            $result = json_decode($response, true);
            if (empty($result['data']['offLotteryConfigBeanList'])) {
                return false;
            }
            $lottery_arr = array();
            foreach ($result['data']['offLotteryConfigBeanList'] as $info) {
                $lottery_arr[$info['lotteryId']] = array(
                    'lotteryId' => $info['lotteryId'],
                    'rewardName' => $info['rewardName'],
                    'rewardNum' => $info['rewardNum'],
                    'duration' => floor((strtotime($info['drawingTime']) - strtotime($info['createTime']))/86400),
                );
            }
            $lottery_id_arr = array_keys($lottery_arr);
            foreach ($lottery_id_arr as $lottery_id) {
                $lottery_id = 201910092200;
                $offset = 0;
                $limit = 100;
                $total = 0;
                $join = 0;
                $service = s('Common');
                while (true) {
                    $url = "https://switch.vgjump.com/switch/lottery/getLotteryTotalNumber?lotteryId={$lottery_id}&offset={$offset}&limit={$limit}";
                    $cookie = 'qiyeToken=eyJhbGciOiJIUzUxMiJ9.eyJpc3MiOiJqdW1wIiwidXNlciI6IkREZUNTUmVmQmdIMVV6QTgifQ.UIs94dUQN0AeC_o_iDis9u6MHul2Qe7VFssI4u-TySHTgtdODr57XJZsYaw0SLPX-2YbO4uCJ_glFpGQH1qz_Q;uid=228053;version=2;';
                    $response = $service->curl($url, array(), '', '', $cookie);
//                                var_dump($url,$response);exit;
                    if (empty($response)) {
                        break;
                    }

                    $result = json_decode($response, true);
                    if (empty($result['data']['lotteryBillboardBeanList'])) {
                        break;
                    }

                    $num_arr = array_column($result['data']['lotteryBillboardBeanList'], 'countNum');
                    $sum = array_sum($num_arr);
                    $join = $result['data']['totalUserNum'];

                    $total += $sum;
                    $offset += $limit;
                }

                $cpa = round($total/$join, 2);
                echo "jump抽奖：{$lottery_id} {$lottery_arr[$lottery_id]['rewardName']}x{$lottery_arr[$lottery_id]['rewardNum']} 耗时：{$lottery_arr[$lottery_id]['duration']}天 参与人数：$join 总票数：$total 人均广告：$cpa \r\n";
            }

            $o += $l;
        }
    }

    public function handleLotteryRank()
    {
        $db = pdo();
        $sql = "select lottery_id,user_id, count(lottery_ticket) as num from lottery_ticket where status = 1 and lottery_id = 13 group by lottery_id,user_id order by lottery_id desc, num desc";
        $list = $db->query($sql);
        $redis = r('psn_redis');

        foreach ($list as $info) {
            $lottery_ticket_rank_key = redis_key('lottery_ticket_rank', $info['lottery_id']);
            $redis->zAdd($lottery_ticket_rank_key, $info['num'], $info['user_id']);
            echo "user_id:{$info['user_id']} lottery_id:{$info['lottery_id']} num:{$info['num']} \r\n";
        }

    }

}
