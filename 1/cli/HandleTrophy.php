<?php
class HandleTrophy extends BaseService
{
    public function syncTrophyTitle()
    {
        $redis = r('psn_redis');
        $sync_mq_key = redis_key('mq_sync_user_trophy_title');
        /** @var  $service TrophyTitleService */
        $service = s('TrophyTitle');

        while ($redis->lLen($sync_mq_key) > 0) {
            $psn_id = $redis->rPop($sync_mq_key);
            $offset = 30;
            $limit = 100;
            $sync_time_whole_key = redis_key('sync_time_trophy_title_whole', $psn_id);
            while (true) {
                $list = $service->syncUserTrophyTitleListFromSony($psn_id, $offset, $limit);
                if ($service->hasError()) {
                    //同步出现异常 则抹去全量同步时间 让用户下次操作 可以重新进行全量同步
                    $redis->expire($sync_time_whole_key, 0);
                    $service->flushError();
                    break;
                }
                if (empty($list)) {
                    $redis->set($sync_time_whole_key, time());
                    break;
                }
                $offset += $limit;
                $time = date('Y-m-d H:i:s');
                echo "{$time}: {$psn_id} 同步奖杯头衔信息成功，偏移值{$offset} \r\n";
                unset($list);
            }
        }
    }

    public function syncTrophyDetail()
    {
        $redis = r('psn_redis');
        $sync_mq_key = redis_key('mq_sync_user_trophy_detail');
        /** @var  $service TrophyDetailService */
        $service = s('TrophyDetail');

        while ($redis->lLen($sync_mq_key) > 0) {
            $json = $redis->rPop($sync_mq_key);
            $data = json_decode($json, true);
            $psn_id = $data['psn_id'];
            $np_communication_id = $data['np_communication_id'];

            $service->syncUserTrophyDetail($psn_id, $np_communication_id);
            if ($service->hasError()) {
                echo "{$psn_id} {$np_communication_id} 奖杯同步异常: {$service->getErrorCode()} {$service->getErrorMsg()} \r\n";
                if ($service->getErrorCode() == 2138635 && $service->getErrorMsg() == 'Rate limit exceeded') {
                    sleep(180);
                    break;
                }
                $service->flushError();
                continue;
            }

            $time = date('Y-m-d H:i:s');
            echo "{$time}: {$psn_id} {$np_communication_id} 同步奖杯详情结束 \r\n";
        }
    }

    public function syncTrophyTips()
    {
        $redis = r('psn_redis');
        $sync_mq_key = redis_key('mq_sync_trophy_tips');
        /** @var  $service TrophyTipsService */
        $service = s('TrophyTips');

        while ($redis->lLen($sync_mq_key) > 0) {
            $json = $redis->rPop($sync_mq_key);
            $data = json_decode($json, true);
            $np_communication_id = $data['np_communication_id'];
            $daily_key = redis_key('daily_sync_trophy_tips_list', date('Ymd'));
            $is_sync = $redis->sIsMember($daily_key, $np_communication_id);
            if ($is_sync === true) {
                echo "{$np_communication_id} 奖杯贴士今日已同步过 \r\n";
                continue;
            }
            $service->syncTrophyTips($np_communication_id);
            if ($service->hasError()) {
                echo "{$np_communication_id} 奖杯贴士同步异常: {$service->getErrorCode()} {$service->getErrorMsg()} \r\n";
                $service->flushError();
                continue;
            }
            $redis->sAdd($daily_key, $np_communication_id);
            $redis->expire($daily_key, time()+86400);

            $time = date('Y-m-d H:i:s');
            echo "{$time}: {$np_communication_id} 同步奖杯贴士结束 \r\n";
        }
    }

    public function addTrophyDetail()
    {
        $db = pdo();
        $sql = "SELECT np_communication_id FROM trophy_title WHERE platform LIKE %PS4% ORDER BY id DESC";
        $list = $db->query($sql);

        if (empty($list)) {
            return false;
        }

        foreach ($list as $item) {
            $service      = s('Profile', 1);
            $data = $service->getGameDetail(self::PSN_ID, $item['np_communication_id']);

        }

    }

    //更新所有用户top100奖杯数据
    public function updateTrophyTitle()
    {
        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        $db = pdo();
        $sql = "select psn_id from account where psn_id is not null and psn_id = 'luobro' order by id desc";
        $list = $db->query($sql);
        $offset = 0;
        $limit = 100;
        $url = "https://cn-tpy.np.community.playstation.net/trophy/v1/trophyTitles?";
        $param = array(
            'npLanguage' => 'en',
            'fields' => '@default,trophyTitleSmallIconUrl',
            'platform' => 'PS4,PSVITA,PS3',
            'returnUrlScheme' => 'http',
            'offset' => $offset,
            'limit' => $limit,
        );

        $service = s('Common');
        $db->tableName = 'trophy_title';

        foreach ($list as $account) {
            $param['comparedUser'] = $account['psn_id'];
            $param_str = http_build_query($param);
            $request = $url . $param_str;
            $header = array(
                "Origin: https://id.sonyentertainmentnetwork.com",
                "Authorization:{$info['token_type']} {$info['access_token']}"
            );

            $json = $service->curl($request, $header);
            if ($service->hasError()) {
                echo "curl fail :" . json_encode($service->getError()) . "\r\n";
                $service->flushError();
                continue;
            }
            $json = $service->uncamelizeJson($json);
            $json =  preg_replace_callback('/\"last_update_date\":\"(.*?)\"/', function($matchs){
                return str_replace($matchs[1], strtotime($matchs[1]), $matchs[0]);
            }, $json);

            $data = json_decode($json, true);
            if (empty($data['trophy_titles'])) {
                continue;
            }

            foreach ($data['trophy_titles'] as $trophy) {
                try {
                    $where['np_communication_id'] = $trophy['np_communication_id'];
                    $result = $db->find($where);
                    if (!empty($result)) {
                        continue;
                    }

                    $np_communication_id = $trophy['np_communication_id'];
                    $name = $trophy['trophy_title_name'];
                    $detail = $trophy['trophy_title_detail'];
                    $name = str_replace('™', '', $name);
                    $name = str_replace('®', '', $name);
                    $detail = str_replace('™', '', $detail);
                    $detail = str_replace('®', '', $detail);

                    $icon_url = $trophy['trophy_title_icon_url'];
                    $small_icon_url = $trophy['trophy_title_small_icon_url'];
                    $platform = $trophy['trophy_title_platfrom'];
                    $has_trophy_group = (int)$trophy['has_trophy_groups'];
                    $bronze = (int)$trophy['defined_trophies']['bronze'];
                    $silver = (int)$trophy['defined_trophies']['silver'];
                    $gold = (int)$trophy['defined_trophies']['gold'];
                    $platinum = (int)$trophy['defined_trophies']['platinum'];

                    $insert_data = array(
                        'np_communication_id' => $np_communication_id,
                        'name' => $name,
                        'detail' => $detail,
                        'icon_url' => $icon_url,
                        'small_icon_url' => $small_icon_url,
                        'platform' => $platform,
                        'has_trophy_group' => $has_trophy_group,
                        'bronze' => $bronze,
                        'silver' => $silver,
                        'gold' => $gold,
                        'platinum' => $platinum,
                        'create_time' => time(),
                    );
                    $db->insert($insert_data);
                } catch (Exception $e) {
                    echo "写入数据库出现异常: {$e->getMessage()} \r\n";
                    continue;
                }
            }

            echo "psn_id:{$account['psn_id']} 奖杯top100处理完成 \r\n";
        }
        echo "任务处理完成";
    }

    //【废弃】通过syncGameTrophyRelation方法关联
    public function gameTrophyRelation()
    {
        $db = pdo();
        $sql = "select a.id,a.game_id, a.origin_name, a.display_name, b.name, b.np_communication_id from game a inner join trophy_title b on a.`origin_name` = b.name where b.platform = 'PS4'  order by id asc";
        $list = $db->query($sql);
        if (empty($list)) {
            return false;
        }

        $db->tableName = 'game';
        foreach ($list as $key => $info) {
            $data['np_communication_id'] = $info['np_communication_id'];
            $where['game_id'] = $info['game_id'];

            $db->update($data, $where);
            $i = $key + 1;
            echo "{$i} 游戏id {$info['game_id']} 成功关联奖杯id {$info['np_communication_id']} \r\n";
            unset($data);
            unset($where);
        }

        echo "任务处理完成";
    }

    public function syncGameTrophyRelation()
    {
        $redis = r('psn_redis');
        $db = pdo();
        $db->tableName = 'game';
        $list = $db->findAll('id > 0', 'id,game_id, np_title_id, display_name', 'id desc');

        $service = s('Profile');
        foreach ($list as $game) {
            $trophy_info = $service->getTrophyInfoByNptitleId($game['np_title_id']);
            if ($service->hasError()) {
                echo "get_np_communiction_id_fail: {$service->getErrorCode()} {$service->getErrorMsg()} \r\n";
                $service->flushError();
                continue;
            }

            if (empty($trophy_info['np_communication_id'])) {
                continue;
            }

            $data['np_communication_id'] = $trophy_info['np_communication_id'];
            $where['game_id'] = $game['game_id'];
            $db->update($data, $where);
            $redis_key = redis_key('relation_game_trophy', $game['game_id']);
            $redis->set($redis_key, $trophy_info['np_communication_id']);
            echo "id: {$game['id']} game_id: {$game['game_id']} np_communication_id: {$trophy_info['np_communication_id']} game_name: {$game['display_name']} trophy_name: {$trophy_info['trophy_title_name']} 游戏奖杯关联成功\r\n";
            sleep(3);
        }

        echo "{date('Y-m-d')}脚本执行完成";
    }

    public function syncGoodsTrophyRelation()
    {
        $db = pdo();
        $db->tableName = 'goods';
        $list = $db->findAll('id > 0', 'id,goods_id, np_title_id, name_cn', 'id desc');

        $service = s('Profile');
        foreach ($list as $goods) {
            $trophy_info = $service->getTrophyInfoByNptitleId($goods['np_title_id']);
            if ($service->hasError()) {
                echo "get_np_communiction_id_fail: {$service->getErrorCode()} {$service->getErrorMsg()} \r\n";
                $service->flushError();
                continue;
            }

            if (empty($trophy_info['np_communication_id'])) {
                continue;
            }

            $data['np_communication_id'] = $trophy_info['np_communication_id'];
            $where['goods_id'] = $goods['goods_id'];
            $db->update($data, $where);
            log::i("id: {$goods['id']} goods_id: {$goods['goods_id']} np_communication_id: {$trophy_info['np_communication_id']} game_name: {$goods['name_cn']} trophy_name: {$trophy_info['trophy_title_name']} 游戏奖杯关联成功");
            echo "id: {$goods['id']} goods_id: {$goods['goods_id']} np_communication_id: {$trophy_info['np_communication_id']} game_name: {$goods['name_cn']} trophy_name: {$trophy_info['trophy_title_name']} 游戏奖杯关联成功\r\n";
            sleep(3);
        }

        echo "脚本执行完成";
    }


}
