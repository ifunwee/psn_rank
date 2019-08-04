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
        $where['status'] = 1;
        $page = 1;
        $limit = 1000;
        $redis = r('psn_redis');

        $is_loop = true;
        while ($is_loop) {
            $start = ($page - 1) * $limit;
            $limit_str = "{$start}, {$limit}";
            $list = $db->findAll($where, '*', 'id desc', $limit_str);

            if (empty($list)) {
                $is_loop = false;
            } else {
                foreach ($list as $info) {
                    $account_follow_key = redis_key('account_follow', $info['open_id']);
                    $goods_follow_key = redis_key('goods_follow', $info['goods_id']);
                    if ($info['goods_id'] == 'undefined') {
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

}
