<?php

/**
 * 用户资料相关信息
 */
class ProfileService extends BaseService
{
    public $expire_time;
    public $cache_mode;

    public function __construct($refresh = 0)
    {
        parent::__construct();
        //凌晨数据过期
        $this->expire_time = strtotime(date("Y-m-d", strtotime("+1 week")));
        $this->cache_mode = c('cache_mode');
        $refresh && $this->cache_mode = false;
    }

    public function getPsnInfo($psn_id)
    {
        if (empty($psn_id)) {
            return $this->setError('param_psn_id_empty', '缺少参数');
        }

        $info = $this->getPsnInfoFromDb($psn_id);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $redis = r('psn_redis');
        $sync_time_key = redis_key('sync_time_profile', $psn_id);
        $sync_time = $redis->get($sync_time_key);
        $info['sync_time'] = $sync_time ? : '';

        return $info;
    }

    public function syncPsnInfo($psn_id)
    {
        if (empty($psn_id)) {
            return $this->setError('param_psn_id_empty', '缺少参数');
        }

        $info = $this->syncPsnInfoFromSony($psn_id);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $redis = r('psn_redis');
        $now = time();
        $sync_time_key = redis_key('sync_time_profile', $psn_id);
        $redis->set($sync_time_key, $now);
        $info['sync_time'] = $now;

        return $info;
    }

    public function getPsnInfoFromDb($psn_id)
    {
        $redis = r('psn_redis');
        $sync_time_key = redis_key('sync_time_profile', $psn_id);
        $sync_time = $redis->get($sync_time_key);

        $db = pdo();
        $db->tableName = 'profile';
        $where['psn_id'] = $psn_id;
        $info = $db->find($where);
        $info['sync_time'] = $sync_time ? : '';
        unset($info['id'], $info['create_time'], $info['update_time']);

        return $info;
    }

    public function syncPsnInfoFromSony($psn_id)
    {
        if (empty($psn_id)) {
            return $this->setError('param_psn_id_empty', '缺少参数');
        }

        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        $url = "https://cn-prof.np.community.playstation.net/userProfile/v1/users/{$psn_id}/profile2?";
        $param = array(
            'languagesUserdLanguageSet' => 'set4',
            'fields' => 'onlineId,avatarUrls,plus,trophySummary(@default,progress,earnedTrophies)',
            'profilePictureSizes' => 'm',
            'avatarSizes' => 'm',
            'titleIconSize' => 's',
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
        $data = json_decode($json, true);

        if (!is_array($data)) {
            log::e(var_export($json, true));
            return $this->setError('get_psn_info_fail', '无法解析的数据格式');
        }

        if ($data['error']) {
            return $this->setError($data['error']['code'], $data['error']['message']);
        }

        $info = array(
            'psn_id' => $data['profile']['online_id'],
            'avatar' => $data['profile']['avatar_urls'][0]['avatar_url'],
            'is_plus' => $data['profile']['plus'],
            'level' => $data['profile']['trophy_summary']['level'] ? : 0,
            'progress' => $data['profile']['trophy_summary']['progress'] ? : 0,
            'platinum' => $data['profile']['trophy_summary']['earned_trophies']['platinum'] ? : 0,
            'gold' => $data['profile']['trophy_summary']['earned_trophies']['gold'] ? : 0,
            'silver' => $data['profile']['trophy_summary']['earned_trophies']['silver'] ? : 0,
            'bronze' => $data['profile']['trophy_summary']['earned_trophies']['bronze'] ? : 0,
        );

        $this->savePsnInfo($info);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        return $info;
    }

    protected function savePsnInfo($info)
    {
        try {
            $db = pdo();
            $db->tableName = 'profile';
            $where['psn_id'] = $info['psn_id'];
            $result = $db->find($where);

            if (empty($result)) {
                $info['create_time'] = time();
                $db->insert($info);
            } else {
                $info['update_time'] = time();
                $db->update($info, $where);
            }
        } catch (Exception $e) {
            log::e("写入数据库出现异常: {$e->getMessage()}");
            return $this->setError('db_error');
        }
    }

    /**
     * 根据psn_id获取用户信息
     *
     * @param $psn_id
     *
     * @return array
     */
    public function getUserInfo($psn_id)
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('user_info', $psn_id);
        $json = $redis->get($redis_key);

        if (!empty($json) && $this->cache_mode) {
            return json_decode($json, true);
        }

        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        $url = "https://cn-prof.np.community.playstation.net/userProfile/v1/users/{$psn_id}/profile2?";
        $param = array(
            'languagesUserdLanguageSet' => 'set4',
            'fields' => 'onlineId,avatarUrls,plus,trophySummary(@default,progress,earnedTrophies)',
            'profilePictureSizes' => 'm',
            'avatarSizes' => 'm',
            'titleIconSize' => 's',
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

        if (!empty($json)) {
            $data = json_decode($json, true);
            if (is_numeric($data['profile']['trophy_summary']['level'])) {
                $avatar_url = $data['profile']['avatar_urls'][0]['avatar_url'];
                unset($data['profile']['avatar_urls']);
                $data['profile']['avatar_url'] = $avatar_url;
                $redis->set($redis_key, json_encode($data['profile']));
                $redis->expireAt($redis_key, $this->expire_time);
            }
            return $data['profile'];
        } else {
            return array();
        }
    }

    public function getUserGameList($psn_id)
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('user_game_list', $psn_id);
        $json = $redis->get($redis_key);

        if (!empty($json) && $this->cache_mode) {
            return json_decode($json, true);
        }

        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        $url = "https://cn-tpy.np.community.playstation.net/trophy/v1/trophyTitles?";
        $param = array(
            'npLanguage' => 'zh-TW',
            'fields' => '@default,trophyTitleSmallIconUrl',
            'platform' => 'PS4,PSVITA,PS3',
            'returnUrlScheme' => 'http',
            'offset' => 0,
            'limit' => 128,
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

        if (!empty($json)) {
            $data = json_decode($json, true);
            if (!empty($data['trophy_titles'])) {
                if (count($data['trophy_titles']) > 20) {
                    $latest_play = array_splice($data['trophy_titles'], 0, 6);
                    foreach ($data['trophy_titles'] as $key => $item) {
                        if ($item["compared_user"]["progress"] > 0) {
                            $sort_arr[] = $item["compared_user"]["progress"];
                        } else {
                            unset($data['trophy_titles'][$key]);
                        }
                    }
                    if (!empty($sort_arr)) {
                        array_multisort($sort_arr, SORT_DESC, $data['trophy_titles']);
                        $data['trophy_titles'] = array_merge($latest_play, $data['trophy_titles']);
                    } else {
                        $data['trophy_titles'] = $latest_play;
                    }
                }

                $redis->set($redis_key, json_encode($data));
                $redis->expireAt($redis_key, $this->expire_time);
            }

            return $data;
        } else {
            return array();
        }
    }

    public function getGameDetail($psn_id, $game_id)
    {
        $redis = r('psn_redis');
        if (is_numeric($game_id)) {
            $redis_key = redis_key('relation_game_trophy', $game_id);
            $game_id = $redis->get($redis_key);
        }

        if (empty($game_id)) {
            return array();
        }

        $redis_key = redis_key('psn_game_detail', $game_id);
        $json = $redis->get($redis_key);

        if (!empty($json) && $this->cache_mode) {
            return json_decode($json, true);
        }

        if (empty($psn_id)) {
            $psn_id = 'funwee';
        }

        $data = $this->getUserGameDetail($psn_id, $game_id, 1);
        return $data;
    }

    public function getGameProgress($psn_id, $game_id)
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('psn_game_progress', $psn_id, $game_id);
        $json = $redis->get($redis_key);

        if (!empty($json) && $this->cache_mode) {
            return json_decode($json, true);
        }

        return $this->getUserGameDetail($psn_id, $game_id, 2);
    }

    public function getUserGameDetail($psn_id, $game_id, $type = 1)
    {
        $data = $this->getUserGameInfo($psn_id, $game_id);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $trophy_total_num = !empty($data['defined_trophies']) ? array_sum($data['defined_trophies']) : 0;
        $trophy_earned_num = 0;
        $earned = $no_earned = $earned_date_arr = array();
        if (empty($data['trophy_groups'])) {
            return array();
        }
        foreach ($data['trophy_groups'] as &$item) {
            unset($item['compared_user']);
            $progress = $this->getUserGameProgress($psn_id, $game_id, $item['trophy_group_id']);
            if ($this->hasError()) {
                continue;
            }
            $trophy_earned = $trophy_no_earned = array();
            foreach ($progress['trophies'] as &$trophy) {
                if ($trophy['compared_user']['earned']) {
                    $trophy_earned['trophy_group_id'] = $item['trophy_group_id'];
                    $trophy_earned['trophy_group_name'] = $item['trophy_group_name'];
                    //获得奖杯的时间集合
                    $earned_date_arr[] = $trophy['compared_user']['earned_date'];
                    unset($trophy['compared_user']);
                    $trophy_earned['trophies'][] = $trophy;
                } else {
                    $trophy_no_earned['trophy_group_id'] = $item['trophy_group_id'];
                    $trophy_no_earned['trophy_group_name'] = $item['trophy_group_name'];
                    unset($trophy['compared_user']);
                    $trophy_no_earned['trophies'][] = $trophy;
                }

                $this->setTrophyInfoToCache($game_id, $trophy['trophy_id'], $trophy);
            }
            //获得的奖杯总数
            $trophy_earned_num += count($trophy_earned['trophies']);
            $item['trophies'] = $progress['trophies'];
            $earned[] = $trophy_earned;
            $no_earned[] = $trophy_no_earned;
            unset($trophy);
        }

        $user_progress = array(
            'complete' => "{$trophy_earned_num}/{$trophy_total_num}",
            'earned' => array_values(array_filter($earned)),
            'no_earned' => array_values(array_filter($no_earned)),
            'first_trophy_earned' => $earned_date_arr ? min($earned_date_arr) : '',
            'last_trophy_earned' =>  $earned_date_arr ? max($earned_date_arr) : '',
        );

        $redis = r('psn_redis');
        if ((int)$type == 1) {
            $redis_key = redis_key('psn_game_detail', $game_id);
            $redis->set($redis_key, json_encode($data));
            return $data;
        } else {
            //如果缓存中的奖杯总数与用户进度的总数不一致 则更新缓存数据
            $redis_key = redis_key('psn_game_detail', $game_id);
            $json = $redis->get($redis_key);
            $cache = json_decode($json, true);
            if (!empty($cache)) {
                $cache_trophy_total_num = array_sum($cache['defined_trophies']);
                if ((int)$cache_trophy_total_num != (int)$trophy_total_num) {
                    $redis_key = redis_key('psn_game_detail', $game_id);
                    $redis->set($redis_key, json_encode($data));
                }
            }

            //更新游戏概况
            $info = array(
                'trophy_title_name' => $data['trophy_title_name'],
                'trophy_title_detail' => $data['trophy_title_detail'],
                'trophy_title_icon_url' => $data['trophy_title_icon_url'],
                'trophy_title_small_icon_url' => $data['trophy_title_small_icon_url'],
                'trophy_title_platfrom' => $data['trophy_title_platfrom'],
                'defined_trophies' => json_encode($data['defined_trophies']),
            );
            $this->setGameOverviewToCache($game_id, $info);

            //缓存用户游戏进度
            $redis_key = redis_key('psn_game_progress', $psn_id, $game_id);
            $redis->set($redis_key, json_encode($user_progress));
            $redis->expireAt($redis_key, $this->expire_time);
            return $user_progress;
        }
    }

    public function getUserGameInfo($psn_id, $game_id)
    {
        if (empty($psn_id)) {
            return $this->setError('param_psn_id_is_empty');
        }

        if (empty($game_id)) {
            return $this->setError('param_game_id_is_empty');
        }

        $redis = r('psn_redis');
        $redis_key = redis_key('user_game_info', $psn_id, $game_id);
        $json = $redis->get($redis_key);

        if (!empty($json) && $this->cache_mode) {
            return json_decode($json, true);
        }

        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        $url = "https://hk-tpy.np.community.playstation.net/trophy/v1/trophyTitles/{$game_id}/trophyGroups?";
        $param = array(
            'npLanguage' => 'zh-TW',
            'fields' => '@default,trophyTitleSmallIconUrl,trophyGroupSmallIconUrl',
            'returnUrlScheme' => 'http',
            'iconSize' => 'm',
//            'comparedUser' => $psn_id,
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
        $redis->set($redis_key, $json);
        $redis->expireAt($redis_key, $this->expire_time);

        $data = json_decode($json, true);
        if ($data['error']) {
            return $this->setError($data['error']['code'], $data['error']['message']);
        }

        if ($data['trophy_title_name']) {
            $info = array(
                'trophy_title_name' => $data['trophy_title_name'],
                'trophy_title_detail' => $data['trophy_title_detail'],
                'trophy_title_icon_url' => $data['trophy_title_icon_url'],
                'trophy_title_small_icon_url' => $data['trophy_title_small_icon_url'],
                'trophy_title_platfrom' => $data['trophy_title_platfrom'],
                'defined_trophies' => json_encode($data['defined_trophies']),
            );
            $this->setGameOverviewToCache($game_id, $info);
        }

        return $data;
    }

    public function getUserGameProgress($psn_id, $game_id, $version_id)
    {
        empty($version_id) && $version_id = 'default';
        $redis = r('psn_redis');
        $redis_key = redis_key('user_game_progress', $psn_id, $game_id, $version_id);
        $json = $redis->get($redis_key);

        if (!empty($json) && $this->cache_mode) {
            return json_decode($json, true);
        }

        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        $url = "https://cn-tpy.np.community.playstation.net/trophy/v1/trophyTitles/{$game_id}/trophyGroups/{$version_id}/trophies?";
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
        $redis->set($redis_key, $json);
        $redis->expireAt($redis_key, $this->expire_time);
        return json_decode($json, true);
    }

    public function getTrophyTips($game_id, $trophy_id, $page)
    {
        $exist = $this->existTrophyTipsFromDb($game_id, $trophy_id);
        if ($exist === true) {
            $list = $this->getTrophyTipsFromDb($game_id, $trophy_id, $page);
        } else {
            $this->getPsnineTips($game_id, $trophy_id);
            $list = $this->getTrophyTipsFromDb($game_id, $trophy_id, $page);
        }

        $result['tips_list'] = $list;
        return $result;
    }

    public function getTrophyInfo($game_id, $trophy_id)
    {
        $game_info = $this->getGameOverviewFromCache($game_id);
        $trophy_info = $this->getTrophyInfoFromCache($game_id, $trophy_id);

        $result['game_info'] = $game_info ? : array();
        $result['trophy_info'] = $trophy_info ? : array();

        return $result;
    }

    public function getPsnId($open_id)
    {
        $info = $this->getAccountInfoFromCache($open_id, array('psn_id'));
        if (empty($info['psn_id'])) {
            $info = $this->getAccountInfoFromDb($open_id, array('psn_id'));

            if (!empty($info['psn_id'])) {
                $redis = r('psn_redis');
                $redis_key = redis_key('account_info', $open_id);
                $redis->hSet($redis_key, 'psn_id', $info['psn_id']);
            }
        }

        $psn_id = $info['psn_id'] ? $info['psn_id'] : '';
        return $psn_id;
    }

    public function bind($open_id, $psn_id)
    {
        if (empty($open_id) || empty($psn_id)) {
            return false;
        }

        $db = pdo();
        $info = $this->getAccountInfoFromDb($open_id);
        if (empty($info)) {
            $data['psn_id'] = $psn_id;
            $data['open_id'] = $open_id;
            $data['create_time'] = time();
            $result = $db->insert($data);
        } else {
            $data['psn_id'] = $psn_id;
            $where['open_id'] = $open_id;
            $data['update_time'] = time();
            $result = $db->update($data, $where);
        }

        if (false !== $result) {
            $redis = r('psn_redis');
            $redis_key = redis_key('account_info', $open_id);
            $redis->hSet($redis_key, 'psn_id', $psn_id);
        }

        return true;
    }

    public function getPsnineTips($game_id, $trophy_id)
    {
        $start = (int)substr($game_id, 4, 5);
        $end = str_pad($trophy_id + 1, 3, 0, STR_PAD_LEFT);
        $id = (string)$start . (string)$end;

        $url = "http://psnine.com/trophy/{$id}";
        $service = s('Common');
        $response = $service->curl($url);
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }


        //替换a标签跳转为#
        $response =  preg_replace_callback('/<a href=\"(.*?)\"/i', function($matchs){
        return str_replace($matchs[1], '#', $matchs[0]);
        }, $response);

        //过滤@xxxxxx
        $preg = '/<a href=\"#\">@(.*?)<\/a>&nbsp;/i';
        $response = preg_replace($preg, '', $response);

        //过滤p9的emoji表情
        /**
        $preg = '/<img src=\"http:\/\/photo.psnine.com\/face\/(.*?)\">/i';
        $response = preg_replace($preg, '', $response);
         */

        //匹配头像
        $preg = '/<a class=\"l\"(.*?)<img src=\"(.*?)\" width/i';
        preg_match_all($preg, $response, $matches);
        $avatar_arr = $matches[2];
        unset($matches);
        //匹配内容
        $preg = '/<div class=\"content pb10\">([\s\S]*?)<\/div>/i';
        preg_match_all($preg, $response, $matches);
        $content_arr = $matches[1];
        unset($matches);
        $preg = '/<a href=\"#\" class=\"psnnode\">(.*?)<\/a>/i';
        preg_match_all($preg, $response, $matches);
        $nickname_arr = $matches[1];

        $list = array();
        $db = pdo();
        $db->tableName = 'trophy_tips';

        if (empty($content_arr)) {
            return $list;
        }

        foreach ($content_arr as $key => $content) {
            $content = strip_tags($content, '<br>');
            $content = preg_replace('/<br\\s*?\/??>/i', chr(13) . chr(10), $content);
            $data = $temp = array(
                'nickname'  => trim($nickname_arr[$key]),
                'avatar'    => trim($avatar_arr[$key]),
                'content'   => trim($content),
            );

            $list[] = $temp;
            $data['game_id'] = $game_id;
            $data['trophy_id'] = $trophy_id;
            $data['source'] = 1;
            $data['create_time'] = time();

            $db->preInsert($data);
        }
        $db->preInsertPost();
        $info['tips_num'] = count($list);
        $this->setTrophyInfoToCache($game_id, $trophy_id, $info);
    }

    protected function getTrophyTipsFromDb($game_id, $trophy_id, $page = 1, $limit = 10)
    {
        $db = pdo();
        $db->tableName = 'trophy_tips';
        $where['game_id'] = $game_id;
        $where['trophy_id'] = $trophy_id;
        $start = ($page - 1 ) * $limit;
        $limit_str = "{$start}, {$limit}";
        $list = $db->findAll($where, 'nickname,avatar,content', 'create_time desc,id asc', $limit_str);

        return $list;
    }

    protected function existTrophyTipsFromDb($game_id, $trophy_id)
    {
        $db = pdo();
        $db->tableName = 'trophy_tips';
        $where['game_id'] = $game_id;
        $where['trophy_id'] = $trophy_id;
        $info = $db->find($where);

        $exist = $info ? true : false;
        return $exist;
    }

    protected function getTrophyInfoFromCache($game_id, $trophy_id)
    {
        $redis = r('psn_redis');
        $trophy_info_key = redis_key('psn_trophy_info', $game_id, $trophy_id);
        return $redis->hGetAll($trophy_info_key);
    }

    protected function setTrophyInfoToCache($game_id, $trophy_id, $info)
    {
        $redis = r('psn_redis');
        $trophy_info_key = redis_key('psn_trophy_info', $game_id, $trophy_id);
        $redis->hMset($trophy_info_key, $info);
    }

    protected function getGameOverviewFromCache($game_id)
    {
        $redis = r('psn_redis');
        $game_overview_key = redis_key('psn_game_overview', $game_id);
        $data = $redis->hGetAll($game_overview_key);
        $data['defined_trophies'] = json_decode($data['defined_trophies'], true);

        return $data;
    }

    protected function setGameOverviewToCache($game_id, $info)
    {
        $redis = r('psn_redis');
        $game_overview_key = redis_key('psn_game_overview', $game_id);
        $redis->hMset($game_overview_key, $info);
    }

    protected function getAccountInfoFromDb($open_id, $field = array())
    {
        $db = pdo();
        $db->tableName = 'account';
        $where['open_id'] = $open_id;
        $info = $db->find($where);

        $data = array();
        if (!empty($field)) {
            foreach ($field as $item) {
                $data[$item] = $info[$item];
            }

            return $data;
        }
        return $info;
    }

    protected function getAccountInfoFromCache($open_id, $field = array())
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('account_info', $open_id);
        if (empty($field)) {
            $info = $redis->hGetAll($redis_key);
        } else {
            $info = $redis->hMGet($redis_key, $field);
        }

        return $info;
    }

    public function getTrophyInfoByNptitleId($np_title_id)
    {
        $service = s('SonyAuth');
        $info = $service->getApiAccessToken();
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        $url = "https://cn-tpy.np.community.playstation.net/trophy/v1/apps/trophyTitles?";
        $param = array(
            'npTitleIds' => $np_title_id,
            'fields' => '@default',
            'npLanguage' => 'zh-TW',
//            'onlineIds' => 'luobro,Dear-Huihui',
        );
        $param_str = http_build_query($param);
        $url = $url . $param_str;
        $header = array(
            "Origin: https://id.sonyentertainmentnetwork.com",
            "Authorization:{$info['token_type']} {$info['access_token']}"
        );

        $service = s('Common');
//        $service->is_proxy = true;
        $json = $service->curl($url, $header);
        if ($service->hasError()) {
//            $service->is_change_proxy = true;
            return $this->setError($service->getError());
        }
        $json = $service->uncamelizeJson($json);
        $data = json_decode($json, true);
        if (!empty($data['error'])) {
            $service->is_change_proxy = true;
            return $this->setError($data['error']['code'], $data['error']['message']);
        }

        if (empty($data['apps'][0]['trophy_titles'][0])) {
            return array();
        }

        return $data['apps'][0]['trophy_titles'][0];
    }

}