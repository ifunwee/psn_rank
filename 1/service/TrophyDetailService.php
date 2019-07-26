<?php
class TrophyDetailService extends BaseService
{
    public $trophy_type = array(
        'platinum' => 1,
        'gold' => 2,
        'silver' => 3,
        'bronze' => 4,
    );

    public function getUserTrophyDetail($psn_id, $np_communication_id)
    {
        $redis = r('psn_redis');
        $sync_time_key = redis_key('sync_time_trophy_detail', $psn_id, $np_communication_id);
        $sync_time = $redis->get($sync_time_key) ? : null;

        $list = $this->getUserTrophyDetailFromDb($psn_id, $np_communication_id);
        if (empty($list)) {
            $data['overview'] = null;
            $data['list'] = null;
            $data['sync_time'] = null;

            return $data;
        }

        $service = s('TrophyTitle');
        $overview = $service->getTrophyTitleInfoFromDb($np_communication_id);

        $data['overview'] = $overview;
        $data['list'] = $list;
        $data['sync_time'] = $sync_time;

        return $data;

    }

    public function syncUserTrophyDetail($psn_id, $np_communication_id)
    {
        $redis = r('psn_redis');
        $sync_time_key = redis_key('sync_time_trophy_detail', $psn_id, $np_communication_id);
        $sync_time = $redis->get($sync_time_key) ? : null;

        $sync_mq_key = redis_key('mq_sync_user_trophy_detail');

        if (time() - (int)$sync_time <= 600) {
            return $this->setError('sync_time_limit', '同步操作过于频繁，请稍后再试');
        }

        $group = $this->syncTrophyGroupInfoFromSony($np_communication_id);
        if ($this->hasError()) {
            $data['psn_id'] = $psn_id;
            $data['np_communication_id'] = $np_communication_id;
            $redis->lPush($sync_mq_key, json_encode($data));
            return $this->setError($this->getError());
        }

        foreach ($group as $info) {
            $this->syncUserTrophyProgress($psn_id, $np_communication_id, $info['group_id']);
            if ($this->hasError()) {
                $data['psn_id'] = $psn_id;
                $data['np_communication_id'] = $np_communication_id;
                $redis->lPush($sync_mq_key, json_encode($data));
                return $this->setError($this->getError());
            }
        }

        $tips = '同步成功';
        $now = time();
        $redis->set($sync_time_key, $now);

        $result['tips'] = $tips;
        return $result;
    }

    public function syncTrophyGroupInfoFromSony($np_communication_id)
    {
        if (empty($np_communication_id)) {
            return $this->setError('param_np_communication_id_is_empty');
        }

        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $service->setError($service->getError());
        }

        $url = "https://hk-tpy.np.community.playstation.net/trophy/v1/trophyTitles/{$np_communication_id}/trophyGroups?";
        $param = array(
            'npLanguage' => 'zh-TW',
            'fields' => '@default,trophyTitleSmallIconUrl,trophyGroupSmallIconUrl',
            'returnUrlScheme' => 'http',
            'iconSize' => 'm',
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
        if ($data['error']) {
            return $this->setError($data['error']['code'], $data['error']['message']);
        }

        if (empty($data['trophy_groups'])) {
            return $this->setError('get_trophy_group_is_empty');
        }

        $group = array();
        foreach ($data['trophy_groups'] as $item)
        {
            $info = array(
                'np_communication_id' => $np_communication_id,
                'group_id' => $item['trophy_group_id'],
                'name' => $item['trophy_group_name'],
                'detail' => $item['trophy_group_detail'],
                'icon_url' => $item['trophy_group_icon_url'],
                'small_icon_url' => $item['trophy_group_small_icon_url'],
                'bronze' => (int)$item['defined_trophies']['bronze'],
                'silver' => (int)$item['defined_trophies']['silver'],
                'gold' => (int)$item['defined_trophies']['gold'],
                'platinum' => (int)$item['defined_trophies']['platinum'],
            );


            $group[] = $info;

            $this->saveTrophyGroupInfo($info);
            if ($this->hasError()) {
                log::n(json_encode($this->getError()));
                $this->flushError();
                continue;
            }
        }

        return $group;
    }

    public function saveTrophyGroupInfo($info)
    {
        $db  = pdo();
        $db->tableName = 'trophy_group';
        try {
            $data = array(
                'np_communication_id' => $info['np_communication_id'],
                'group_id' => $info['group_id'],
                'name' => $info['name'],
                'detail' => $info['detail'],
                'icon_url' => $info['icon_url'],
                'small_icon_url' => $info['small_icon_url'],
                'bronze' => (int)$info['bronze'],
                'silver' => (int)$info['silver'],
                'gold' => (int)$info['gold'],
                'platinum' => (int)$info['platinum'],
            );

            $where['np_communication_id'] = $info['np_communication_id'];
            $where['group_id'] = $info['group_id'];
            $result = $db->find($where);

            if (empty($result)) {
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
    }

    public function syncUserTrophyProgress($psn_id, $np_communication_id, $group_id)
    {
        empty($group_id) && $group_id = 'default';
        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $service->setError($service->getError());
        }

        $url = "https://cn-tpy.np.community.playstation.net/trophy/v1/trophyTitles/{$np_communication_id}/trophyGroups/{$group_id}/trophies?";
        $param = array(
            'npLanguage' => 'zh-TW',
            'fields' => '@default,trophyRare,trophyEarnedRate,hasTrophyGroups,trophySmallIconUrl',
            'returnUrlScheme' => 'http',
            'iconSize' => 'm',
            'visibleType' => 1,
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
        $json =  preg_replace_callback('/\"earned_date\":\"(.*?)\"/', function($matchs){
            return str_replace($matchs[1], strtotime($matchs[1]), $matchs[0]);
        }, $json);

        $data = json_decode($json, true);
        if ($data['error']) {
            return $this->setError($data['error']['code'], $data['error']['message']);
        }

        $list = array();

        foreach ($data['trophies'] as $item) {
            $info = array(
                'trophy_id' => $item['trophy_id'],
                'is_hidden' => $item['trophy_hidden'] === true ? 1 : 0,
                'type' => $item['trophy_type'],
                'name' => $item['trophy_name'],
                'detail' => $item['trophy_detail'],
                'icon_url' => $item['trophy_icon_url'],
                'small_icon_url' => $item['trophy_small_icon_url'],
                'rare' => $item['trophy_rare'],
                'earned_rate' =>$item['trophy_earned_rate'],
            );
            $list[] = $info;

            $this->saveTrophyInfo($np_communication_id, $group_id, $info);
            if ($this->hasError()) {
                log::n(json_encode($this->getError()));
                $this->flushError();
                continue;
            }

            $psn_id = $item['compared_user']['online_id'];
            if (empty($psn_id)) {
                continue;
            }
            $info['np_communication_id'] = $np_communication_id;
            $info['group_id'] = $group_id;
            $info['trophy_id'] = $item['trophy_id'];
            $info['is_earn'] = $item['compared_user']['earned'] === true ? 1 : 0;
            $info['earn_time'] = $item['compared_user']['earned_date'] ? : 0;

            $this->saveUserTrophyInfo($psn_id, $info);
        }
    }

    public function saveTrophyInfo($np_communication_id, $group_id, $info)
    {
        try {
            $db  = pdo();
            $db->tableName = 'trophy_info';

            $data = array(
                'np_communication_id' => $np_communication_id,
                'group_id' => $group_id,
                'trophy_id' => $info['trophy_id'],
                'is_hidden' => $info['is_hidden'],
                'type' => $info['type'],
                'name' => $info['name'],
                'detail' => $info['detail'],
                'icon_url' => $info['icon_url'],
                'small_icon_url' => $info['small_icon_url'],
                'rare' => $info['rare'],
                'earned_rate' =>$info['earned_rate'],
            );

            $cache = $data;

            $where['np_communication_id'] = $np_communication_id;
            $where['group_id'] = $group_id;
            $where['trophy_id'] = $info['trophy_id'];
            $result = $db->find($where);

            if (empty($result)) {
                $data['create_time'] = time();
                $db->insert($data);
            } else {
                $data['update_time'] = time();
                $db->update($data, $where);
            }
        } catch (Exception $e) {
            log::e("写入数据库出现异常: {$e->getMessage()}");

            return $this->setError('db_error');
        }

        $redis = r('psn_redis');
        $redis_key = redis_key('trophy_info', $np_communication_id, $info['trophy_id']);
        $redis->hMset($redis_key, $cache);
    }

    public function saveUserTrophyInfo($psn_id, $info)
    {
        try {
            $db  = pdo();
            $db->tableName = 'trophy_info_user';
            $data = array(
                'psn_id' => $psn_id,
                'np_communication_id' => $info['np_communication_id'],
                'group_id' => $info['group_id'],
                'trophy_id' => $info['trophy_id'],
                'is_earn' => $info['is_earn'],
                'earn_time' => $info['earn_time'],
            );

            $where['psn_id'] = $psn_id;
            $where['np_communication_id'] = $info['np_communication_id'];
            $where['group_id'] = $info['group_id'];
            $where['trophy_id'] = $info['trophy_id'];
            $result = $db->find($where);

            if (empty($result)) {
                $data['create_time'] = time();
                $db->insert($data);
            } else {
                $data['update_time'] = time();
                $db->update($data, $where);
            }
        } catch (Exception $e) {
            log::e("写入数据库出现异常: {$e->getMessage()}");

            return $this->setError('db_error');
        }

        $cache = array(
            'group_id' => $data['group_id'],
            'is_earn' => $data['is_earn'],
            'earn_time' => $data['earn_time'],
        );
        $redis = r('psn_redis');
        $redis_key = redis_key('trophy_info_user', $psn_id, $info['np_communication_id'], $info['trophy_id']);
        $redis->hMset($redis_key, $cache);
    }

    public function getUserTrophyDetailFromDb($psn_id, $np_communication_id)
    {
        $db = pdo();
        $db->tableName = 'trophy_group';
        $where['np_communication_id'] = $np_communication_id;
        $group_list = $db->findAll($where, 'group_id, name', 'id asc');

        $db->tableName = 'trophy_info';
        $trophy_list = $db->findAll($where, '*', 'id asc');

        $db->tableName = 'trophy_info_user';
        $condition['psn_id'] = $psn_id;
        $condition['np_communication_id'] = $np_communication_id;
        $trophy_progress = $db->findAll($condition);

        $trophy_progress_hash = array();
        if (!empty($trophy_progress)) {
            foreach ($trophy_progress as $item) {
                $trophy_progress_hash[$item['trophy_id']] = array(
                    'is_earn' => $item['is_earn'] ? : '0',
                    'earn_time' => $item['earn_time'] ? : '0',
                );
            }
        }

        $trophy_list_hash = array();
        if (!empty($trophy_list)) {
            $service = s('TrophyTips');
            $trophy_tips = $service->getTrophyTipsNumByNpCommId($np_communication_id);

            unset($item);
            foreach ($trophy_list as $item) {
                $item['is_earn'] = $trophy_progress_hash[$item['trophy_id']]['is_earn'] ? : '0';
                $item['earn_time'] = $trophy_progress_hash[$item['trophy_id']]['earn_time'] ? : '0';
                $item['tips_num'] = $trophy_tips[$item['trophy_id']] ? : 0;
                unset($item['id'],$item['create_time'],$item['update_time']);
                $trophy_list_hash[$item['group_id']][] = $item;
            }
        }


        if (!empty($group_list)) {
            foreach ($group_list as &$item) {
                $item['trophy'] = $trophy_list_hash[$item['group_id']];
            }
            unset($item);
        }

        return $group_list;
    }

    protected function getTrophyInfoFromCache($np_communication_id, $trophy_id)
    {
        $redis = r('psn_redis');
        $trophy_info_key = redis_key('psn_trophy_info', $np_communication_id, $trophy_id);
        return $redis->hGetAll($trophy_info_key);
    }

    protected function setTrophyInfoToCache($np_communication_id, $trophy_id, $info)
    {
        $redis = r('psn_redis');
        $trophy_info_key = redis_key('psn_trophy_info', $np_communication_id, $trophy_id);
        $redis->hMset($trophy_info_key, $info);
    }
}
