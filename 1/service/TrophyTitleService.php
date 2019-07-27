<?php
class TrophyTitleService extends BaseService
{
    public function getUserTrophyTitleList($psn_id, $sort_type , $page = 1)
    {
        if (empty($psn_id)) {
            return $this->setError('param_psn_id_is_empty', '缺少参数');
        }

        $redis = r('psn_redis');
        $sync_time_key = redis_key('sync_time_trophy_title_part', $psn_id);
        $sync_time_whole_key = redis_key('sync_time_trophy_title_whole', $psn_id);
        $sync_time = $redis->get($sync_time_key) ? : null;
        $sync_time_whole = $redis->get($sync_time_whole_key) ? : null;

        $data = $this->getUserTrophyTitleListFromDb($psn_id, $sort_type, $page);
        $list = array();
        if (empty($data)) {
            $result['list'] = $list;
            $result['sync_time'] = $sync_time;
            $result['sync_time_whole'] = $sync_time_whole;
            return $result;
        }

        $np_communication_id_arr = array_column($data, 'np_communication_id');
        $trophy_info = $this->getTrophyTitleInfoFromDb($np_communication_id_arr);
        foreach ($data as $item) {
            $np_communication_id = $item['np_communication_id'];
            $redis_key = redis_key('relation_trophy_game', $np_communication_id);
            $game_id = $redis->get($redis_key) ? : '';

            $info = array(
                'game_id' => $game_id,
                'np_communication' => $np_communication_id,
                'name' => $trophy_info[$np_communication_id]['name'],
                'detail' => $trophy_info[$np_communication_id]['detail'],
                'icon_url' => $trophy_info[$np_communication_id]['icon_url'],
                'small_icon_url' => $trophy_info[$np_communication_id]['small_icon_url'],
                'platform' => $trophy_info[$np_communication_id]['platform'],
                'has_trophy_group' => $trophy_info[$np_communication_id]['has_trophy_group'] ? '1' : '0',
            );

            $info['defined_trophy'] = array(
                'bronze' =>  $trophy_info[$np_communication_id]['bronze'],
                'silver' =>  $trophy_info[$np_communication_id]['silver'],
                'gold' =>  $trophy_info[$np_communication_id]['gold'],
                'platinum' =>  $trophy_info[$np_communication_id]['platinum'],
            );

            $info['user_trophy'] = array(
                'progress' => $item['progress'],
                'bronze' => $item['bronze'],
                'silver' =>  $item['silver'],
                'gold' =>  $item['gold'],
                'platinum' => $item['platinum'],
                'update_time' => $item['update_time'],
            );

            $list[] = $info;
        }

        $result['list'] = $list;
        $result['sync_time'] = $sync_time;
        $result['sync_time_whole'] = $sync_time_whole;
        return $result;
    }

    public function syncUserTrophyTitle($psn_id)
    {
        if (empty($psn_id)) {
            return $this->setError('param_psn_id_empty', '缺少参数');
        }

        $redis = r('psn_redis');
        $sync_time_key = redis_key('sync_time_trophy_title_part', $psn_id);
        $sync_time_whole_key = redis_key('sync_time_trophy_title_whole', $psn_id);
        $sync_time = $redis->get($sync_time_key) ? : null;
        $sync_time_whole = $redis->get($sync_time_whole_key) ? : null;

        $tips = '同步成功';
        if (empty($sync_time) || time() - (int)$sync_time_whole > 86400 * 10) {
            //首次同步 或者 10天没全量同步过 则推入队列做全量更新
            $sync_mq_key = redis_key('mq_sync_user_trophy_title');
            $redis->lPush($sync_mq_key, $psn_id);
            $tips = '已为您同步近期数据，历史数据将在后台持续为您同步';
        } else {
            //非首次 则更新最近30个游戏
            if (time() - (int)$sync_time <= 600) {
                return $this->setError('sync_time_limit', '同步操作过于频繁，请稍后再试');
            }
        }

        $list = $this->syncUserTrophyTitleListFromSony($psn_id, 0, 30);
        if ($this->hasError()) {
            return $this->getError();
        }

        if (!empty($list)) {
            $sync_mq_key = redis_key('mq_sync_user_trophy_detail');
            $data['psn_id'] = $psn_id;
            if (empty($sync_time)) {
                //首次同步则不对比奖杯 直接推入奖杯详情同步队列
                foreach ($list as $trophy) {
                    $data['np_communication_id'] = $trophy['np_communication_id'];
                    $redis->lPush($sync_mq_key, json_encode($data));
                }
            } else {
                foreach ($list as $trophy) {
                    $redis_key = redis_key('trophy_title_user', $psn_id, $trophy['np_communication_id']);
                    $origin_progress = $redis->hGet($redis_key, 'progress') ? : 0;
                    if ($trophy['user_trophy']['progress'] > $origin_progress) {
                        $data['np_communication_id'] = $trophy['np_communication_id'];
                        $redis->lPush($sync_mq_key, json_encode($data));
                    } else {
                        log::n("{$psn_id} {$trophy['np_communication_id']} 进度未改变，不推入同步详情任务");
                    }
                }
            }
            log::i("{$psn_id} 顺利进入奖杯详情同步流程");
        }

        $now = time();
        $redis->set($sync_time_key, $now);
        $result['tips'] = $tips;

        return $result;
    }

    public function getUserTrophyTitleListFromDb($psn_id, $sort_type, $page = 1)
    {
        if (empty($psn_id)) {
            return $this->setError('param_psn_id_is_empty');
        }

        switch ($sort_type) {
            case 'time':
                $sort = 'last_play_time desc'; break;
            case 'progress':
                $sort = 'progress desc, last_play_time desc'; break;
            default:
                $sort = 'update_time desc'; break;
        }

        $where = "psn_id = '{$psn_id}'";
        $list = $this->getTrophyTitleListFromDb($where, array(), $sort, $page);

        return $list;
    }

    private function getTrophyTitleListFromDb($where, $field = array(), $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? implode(',', $field) : '*';
        $sort = $sort ? $sort : 'update_time desc';
        $page = $page ? $page : 1;
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'trophy_title_user';
        $list = $db->findAll($where, $field, $sort, $limit_str);

        return $list;
    }

    public function syncUserTrophyTitleListFromSony($psn_id, $offset = 0, $limit = 128)
    {
        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $service->setError($service->getError());
        }

        $url = "https://cn-tpy.np.community.playstation.net/trophy/v1/trophyTitles?";
        $param = array(
            'npLanguage' => 'zh-TW',
            'fields' => '@default,trophyTitleSmallIconUrl',
            'platform' => 'PS4,PSVITA,PS3',
            'returnUrlScheme' => 'http',
            'offset' => $offset,
            'limit' => $limit,
            'comparedUser' => $psn_id,
        );
        $param_str = http_build_query($param);
        $url = $url . $param_str;
        $header = array(
            "Origin: https://id.sonyentertainmentnetwork.com",
            "Authorization:{$info['token_type']} {$info['access_token']}"
        );

        $service = s('Common');
        $json = $service->curl($url, $header);
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }
        $json = $service->uncamelizeJson($json);
        $json =  preg_replace_callback('/\"last_update_date\":\"(.*?)\"/', function($matchs){
            return str_replace($matchs[1], strtotime($matchs[1]), $matchs[0]);
        }, $json);

        $data = json_decode($json, true);
        $list = array();
        if (empty($data['trophy_titles'])) {
            return $list;
        }

        foreach ($data['trophy_titles'] as $item) {
            $info = array(
                'np_communication_id' => $item['np_communication_id'],
                'name' => $item['trophy_title_name'],
                'detail' => $item['trophy_title_detail'],
                'icon_url' => $item['trophy_title_icon_url'],
                'small_icon_url' => $item['trophy_title_small_icon_url'],
                'platform' => $item['trophy_title_platfrom'],
                'has_trophy_group' => $item['has_trophy_groups'] ? 1 : 0,
                'defined_trophy' => $item['defined_trophies'],
            );

            $this->saveTrophyTitleInfo($info);
            if ($this->hasError()) {
                Log::n($this->getErrorCode() . $this->getErrorMsg());
                $this->flushError();
                continue;
            }

            $info['user_trophy'] = array(
                'progress' => $item['compared_user']['progress'],
                'bronze' => $item['compared_user']['earned_trophies']['bronze'],
                'silver' => $item['compared_user']['earned_trophies']['silver'],
                'gold' => $item['compared_user']['earned_trophies']['gold'],
                'platinum' => $item['compared_user']['earned_trophies']['platinum'],
                'last_play_time' => $item['compared_user']['last_update_date'],
            );

            $list[] = $info;


            $this->saveUserTrophyTitle($item['compared_user']['online_id'], $item['np_communication_id'], $info['user_trophy']);
            if ($this->hasError()) {
                Log::n($this->getErrorCode() . $this->getErrorMsg());
                $this->flushError();
                continue;
            }
        }

        return $list;
    }

    public function saveUserTrophyTitle($psn_id, $np_communication_id, $data)
    {
        if (empty($psn_id)) {
            return $this->setError('param_psn_id_is_empty');
        }

        if (empty($np_communication_id)) {
            return $this->setError('param_np_communication_id_is_empty');
        }

        try {
            $db = pdo();
            $db->tableName = 'trophy_title_user';
            $where['psn_id'] = $psn_id;
            $where['np_communication_id'] = $np_communication_id;
            $info = $db->find($where);

            $data = array(
                'psn_id' => $psn_id,
                'np_communication_id' => $np_communication_id,
                'progress' => $data['progress'] ? : 0,
                'bronze' => $data['bronze'] ? : 0,
                'silver' => $data['silver'] ? : 0,
                'gold' => $data['gold'] ? : 0,
                'platinum' => $data['platinum'] ? : 0,
                'last_play_time' => $data['last_play_time'] ? : 0,
            );

            if (empty($info)) {
                $data['create_time'] = time();
                $db->insert($data);
            } else {
                $data['update_time'] = time();
                $db->update($data, $where);
            }
        } catch (Exception $e) {
            log::e("db_error: {$e->getCode()} {$e->getMessage()}");
            return $this->setError('db_error', '数据库执行异常');
        }

        $cache = array(
            'progress' => $data['progress'] ? : 0,
            'bronze' => $data['bronze'] ? : 0,
            'silver' => $data['silver'] ? : 0,
            'gold' => $data['gold'] ? : 0,
            'platinum' => $data['platinum'] ? : 0,
            'last_play_time' => $data['last_play_time'] ? : 0,
        );
        $redis = r('psn_redis');
        $redis_key = redis_key('trophy_title_user', $psn_id, $np_communication_id);
        $redis->hMset($redis_key, $cache);
    }

    public function saveTrophyTitleInfo($trophy)
    {
        try {
            $db = pdo();
            $db->tableName = 'trophy_title';
            $where['np_communication_id'] = $trophy['np_communication_id'];
            $info = $db->find($where);

            $data = array(
                'np_communication_id' => $trophy['np_communication_id'],
                'name' => $trophy['name'],
                'detail' => $trophy['detail'],
                'icon_url' => $trophy['icon_url'],
                'small_icon_url' => $trophy['small_icon_url'],
                'platform' => $trophy['platform'],
                'has_trophy_group' => (int)$trophy['has_trophy_group'],
                'bronze' => (int)$trophy['defined_trophy']['bronze'],
                'silver' => (int)$trophy['defined_trophy']['silver'],
                'gold' => (int)$trophy['defined_trophy']['gold'],
                'platinum' => (int)$trophy['defined_trophy']['platinum'],
            );
            $cache = $data;

            if (empty($info)) {
                $data['create_time'] = time();
                $db->insert($data);
            } else {
                $data['update_time'] = time();
                $db->update($data, $where);
            }
        } catch (Exception $e) {
            log::e("写入数据库出现异常: {$e->getMessage()}");
            return $this->setError($e->getCode(), $e->getMessage());
        }

        $redis = r('psn_redis');
        $redis_key = redis_key('trophy_title', $trophy['np_communication_id']);
        $redis->hMset($redis_key, $cache);
    }

    public function getTrophyTitleInfoFromDb($np_communication_id, $field = array())
    {
        if (empty($np_communication_id)) {
            return $this->setError('param_np_communication_id_is_empty');
        }

        $db = pdo();
        $db->tableName = 'trophy_title';
        $field = $field ? implode(',', $field) : '*';
        $result = array();
        if (is_array($np_communication_id)) {
            $np_communication_id = array_unique($np_communication_id);
            $np_communication_id_str = implode("','", $np_communication_id);
            $where = "np_communication_id in ('{$np_communication_id_str}')";
            $list = $db->findAll($where, $field);
            foreach ($list as $trophy_title) {
                $result[$trophy_title['np_communication_id']] = $trophy_title;
                unset($trophy_title['id'], $trophy_title['create_time'], $trophy_title['update_time']);
            }
        } else {
            $where = "np_communication_id = '{$np_communication_id}'";
            $result = $db->find($where, $field);
            unset($result['id'], $result['create_time'], $result['update_time']);
        }

        return $result;
    }

}
