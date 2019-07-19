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
        $sync_time = $redis->get($sync_time_key);

        $list = $this->getUserTrophyDetailFromDb($psn_id, $np_communication_id);
        if (empty($list)) {
            return array();
        }

        $trophy_earn_num = $trophy_total_num = 0;
        $earn = $no_earn = $earn_time_arr = array();
        foreach ($list as $item) {
            $trophy_earn = $trophy_no_earn = array();
            foreach ($item['trophy'] as &$info) {
                if ((int)$info['is_earn'] == 1) {
                    $trophy_earn['group_id'] = $item['group_id'];
                    $trophy_earn['name'] = $item['name'];
                    //获得奖杯的时间集合
                    $earn_time_arr[] = $info['earn_time'];
                    $trophy_earn['trophy'][] = $info;
                    $trophy_earn_num++;
                } else {
                    $trophy_no_earn['group_id'] = $item['group_id'];
                    $trophy_no_earn['name'] = $item['name'];
                    $trophy_no_earn['trophy'][] = $info;
                }
                $trophy_total_num++;
            }

            $earn[] = $trophy_earn;
            $no_earn[] = $trophy_no_earn;
        }

        $user_progress = array(
            'complete' => "{$trophy_earn_num}/{$trophy_total_num}",
            'earn' => array_values(array_filter($earn)),
            'no_earn' => array_values(array_filter($no_earn)),
            'first_trophy_earn' => $earn_time_arr ? min($earn_time_arr) : '',
            'last_trophy_earn' =>  $earn_time_arr ? max($earn_time_arr) : '',
            'sysc_time' => $sync_time ? : '',
        );

        return $user_progress;

    }

    public function syncUserTrophyDetail($psn_id, $np_communication_id)
    {
        $redis = r('psn_redis');
        $sync_time_key = redis_key('sync_time_trophy_detail', $psn_id, $np_communication_id);
        $sync_time = $redis->get($sync_time_key);

        if (time() - (int)$sync_time <= 600) {
            return $this->setError('sync_time_limit', '同步操作过于频繁，请稍后再试');
        }

        $group = $this->syncTrophyGroupInfoFromSony($np_communication_id);
        if (empty($group)) {
            return $this->setError('sync_trophy_group_fail');
        }

        foreach ($group as $info) {
            $this->syncUserTrophyProgressFromSony($psn_id, $np_communication_id, $info['group_id']);
            if ($this->hasError()) {
                log::e("syncUserTrophyProgress fail: {$psn_id} {$np_communication_id} {$info['group_id']} ".json_encode($this->getError()));
                continue;
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
                'np_communication_id' => $item['np_communication_id'],
                'group_id' => $item['group_id'],
                'name' => $item['name'],
                'detail' => $item['detail'],
                'icon_url' => $item['icon_url'],
                'small_icon_url' => $item['small_icon_url'],
                'bronze' => (int)$item['defined_trophy']['bronze'],
                'silver' => (int)$item['defined_trophy']['silver'],
                'gold' => (int)$item['defined_trophy']['gold'],
                'platinum' => (int)$item['defined_trophy']['platinum'],
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

    public function syncUserTrophyProgressFromSony($psn_id, $np_communication_id, $group_id)
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
            $where['np_communication_id'] = $np_communication_id;
            $where['group_id'] = $group_id;
            $exist = $db->find($where);

            if ($exist) {
                return true;
            }

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

            return $this->setError($e->getCode(), $e->getMessage());
        }
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

            return $this->setError($e->getCode(), $e->getMessage());
        }
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
        foreach ($trophy_progress as $item) {
            $trophy_progress_hash[$item['trophy_id']] = array(
                'is_earn' => $item['is_earn'],
                'earn_time' => $item['earn_time'],
            );
        }

        $trophy_list_hash = array();
        foreach ($trophy_list as $item) {
            $item['is_earn'] = $trophy_progress_hash[$item['trophy_id']]['is_earn'];
            $item['earn_time'] = $trophy_progress_hash[$item['trophy_id']]['earn_time'];
            unset($item['id'],$item['create_time'],$item['update_time']);
            $trophy_list_hash[$item['group_id']][] = $item;
        }

        foreach ($group_list as &$item) {
            $item['trophy'] = $trophy_list_hash[$item['group_id']];
        }
        unset($item);

        return $group_list;
    }
}
