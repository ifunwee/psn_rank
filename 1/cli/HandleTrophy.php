<?php
class HandleTrophy extends BaseService
{
    const PSN_ID = 'nmxwzy';
    public function addTrophyTitle()
    {
        $service = s('Profile');
        $info = $service->getApiAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $is_loop = true;
        $offset = 0;
        $limit = 100;
        $url = "https://cn-tpy.np.community.playstation.net/trophy/v1/trophyTitles?";
        $param = array(
            'npLanguage' => 'zh-CN',
            'fields' => '@default,trophyTitleSmallIconUrl',
            'platform' => 'PS4,PSVITA,PS3',
            'returnUrlScheme' => 'http',
            'offset' => $offset,
            'limit' => $limit,
            'comparedUser' => self::PSN_ID,
        );

        $service = s('Common');
        while ($is_loop) {
            $param_str = http_build_query($param);
            $request = $url . $param_str;
            $header = array(
                "Origin: https://id.sonyentertainmentnetwork.com",
                "Authorization:{$info['token_type']} {$info['access_token']}"
            );

            $json = $service->curl($request, $header);
            $json = $service->uncamelizeJson($json);
            $json =  preg_replace_callback('/\"last_update_date\":\"(.*?)\"/', function($matchs){
                return str_replace($matchs[1], strtotime($matchs[1]), $matchs[0]);
            }, $json);

            $data = json_decode($json, true);
            if (empty($data['trophy_titles'])) {
                $is_loop = false;
                echo "任务处理完成";
            } else {
                $now = time();
                $db = pdo();
                foreach ($data['trophy_titles'] as $trophy) {
                    try {
                        $np_communication_id = $trophy['np_communication_id'];
                        $name = addslashes($trophy['trophy_title_name']);
                        $detail = addslashes($trophy['trophy_title_detail']);
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

                        $sql = "insert into trophy_title (np_communication_id, name, detail, icon_url, small_icon_url, platform, has_trophy_group, bronze, silver, gold, platinum, create_time)
                        values('{$np_communication_id}', '{$name}', '{$detail}', '{$icon_url}', '{$small_icon_url}', '{$platform}', {$has_trophy_group}, {$bronze}, {$silver}, {$gold}, '{$platinum}', {$now})
                        on duplicate key update update_time = {$now}";

                        $db->exec($sql);
                    } catch (Exception $e) {
                        echo "写入数据库出现异常: {$e->getMessage()} \r\n";
                        continue;
                    }
                    echo "写入数据库成功：{$trophy['np_communication_id']} \r\n";
                }
                $param['offset'] += $limit;
            }
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
        $service = s('Profile');
        $info = $service->getApiAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $db = pdo();
        $sql = "select psn_id from account where psn_id is not null and psn_id <> '' and id <= 3120 order by id desc";
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
                    $name = addslashes($trophy['trophy_title_name']);
                    $detail = addslashes($trophy['trophy_title_detail']);
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

    public function syncGameTrophyRelationToCache()
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


}
