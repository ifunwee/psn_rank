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
        $this->expire_time = strtotime(date('Ymd')) + 86400 + 3600 * 4;
        $this->cache_mode = c('cache_mode');
        $refresh && $this->cache_mode = false;
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

        $info = $this->getApiAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
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
        $json = $service->uncamelizeJson($json);

        if (!empty($json)) {
            $data = json_decode($json, true);
            if (!empty($data['profile'])) {
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

        $info = $this->getApiAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
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
        $json = $service->uncamelizeJson($json);
        $json =  preg_replace_callback('/\"last_update_date\":\"(.*?)\"/', function($matchs){
            return str_replace($matchs[1], strtotime($matchs[1]), $matchs[0]);
        }, $json);

        if (!empty($json)) {
            $data = json_decode($json, true);
            if (!empty($data['trophy_titles'])) {
                if (count($data['trophy_titles']) > 1) {
                    $latest_play = array_slice($data['trophy_titles'], 0, 3);
                    array_splice($data['trophy_titles'], 0, 3);
//                    $latest_play = array_shift($data['trophy_titles']);
                    foreach ($data['trophy_titles'] as $key => $item) {
                        if ($item["compared_user"]["progress"] > 0) {
                            $sort_arr[] = $item["compared_user"]["progress"];
                        } else {
                            unset($data['trophy_titles'][$key]);
                        }
                    }
//                    $sort_arr = array_map(create_function('$item', 'return $item["compared_user"]["progress"];'), $data['trophy_titles']);
                    array_multisort($sort_arr, SORT_DESC, $data['trophy_titles']);
//                    array_unshift($data['trophy_titles'], $latest_play);
                    $data['trophy_titles'] = array_merge($latest_play, $data['trophy_titles']);
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
        $redis_key = redis_key('psn_game_detail', $game_id);
        $json = $redis->get($redis_key);

        if (!empty($json) && $this->cache_mode) {
            return json_decode($json, true);
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
            return $this->getError();
        }

        $trophy_total_num = array_sum($data['defined_trophies']);
        $trophy_earned_num = 0;
        $earned = $no_earned = $earned_date_arr = array();
        if (empty($data['trophy_groups'])) {
            return array();
        }
        foreach ($data['trophy_groups'] as &$item) {
            unset($item['compared_user']);
            $progress = $this->getUserGameProgress($psn_id, $game_id, $item['trophy_group_id']);
            $trophy_earned = $trophy_no_earned = array();
            foreach ($progress['trophies'] as &$trophy) {
                if ($trophy['compared_user']['earned']) {
                    $trophy_earned['trophy_group_id'] = ['trophy_group_id'];
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
            $redis_key = redis_key('psn_game_progress', $psn_id, $game_id);
            $redis->set($redis_key, json_encode($user_progress));
            return $user_progress;
        }
    }

    public function getUserGameInfo($psn_id, $game_id)
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('user_game_info', $psn_id, $game_id);
        $json = $redis->get($redis_key);

        if (!empty($json) && $this->cache_mode) {
            return json_decode($json, true);
        }

        $info = $this->getApiAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $url = "https://hk-tpy.np.community.playstation.net/trophy/v1/trophyTitles/{$game_id}/trophyGroups?";
        $param = array(
            'npLanguage' => 'zh-TW',
            'fields' => '@default,trophyTitleSmallIconUrl,trophyGroupSmallIconUrl',
            'returnUrlScheme' => 'http',
            'iconSize' => 'm',
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

        $info = $this->getApiAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
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
        $json = $service->uncamelizeJson($json);
        $json =  preg_replace_callback('/\"earned_date\":\"(.*?)\"/', function($matchs){
            return str_replace($matchs[1], strtotime($matchs[1]), $matchs[0]);
        }, $json);
        $redis->set($redis_key, $json);
        $redis->expireAt($redis_key, $this->expire_time);
        return json_decode($json, true);
    }

    public function getTrophyTips($game_id, $trophy_id)
    {
        $game_info = $this->getGameOverviewFromCache($game_id);
        $trophy_info = $this->getTrophyInfoFromCache($game_id, $trophy_id);
        if (empty($game_info) || empty($trophy_info)) {
            return $this->setError('game_id_or_trophy_id_is_invalid', '非法的参数');
        }

        $list = $this->getTrophyTipsFromDb($game_id, $trophy_id);
        if (empty($list)) {
            $list = $this->getPsnineTips($game_id, $trophy_id);
        }
        $result['game_info'] = $game_info;
        $result['trophy_info'] = $trophy_info;
        $result['tips_list'] = $list;
        return $result;
    }

    public function bind($open_id, $psn_id)
    {
        if (empty($open_id) || empty($psn_id)) {
            return false;
        }

        $db = pdo();
        $db->tableName = 'account';
        $where['open_id'] = $open_id;
        $info = $db->find($where);
        if (empty($info)) {
            return $this->setError('bind_error_open_id_invalid', 'psn账号绑定异常');
        }
        $data['psn_id'] = $psn_id;
        $result = $db->update($data, $where);

        if (false !== $result) {
            $redis = r('psn_redis');
            $redis_key = redis_key('account_info', $open_id);
            $redis->hSet($redis_key, 'psn_id', $psn_id);
        }

        return true;
    }

    public function getCaptcha()
    {
        $url = "https://auth.api.sonyentertainmentnetwork.com/2.0/captcha";
        $post_data = array(
            'width' => 300,
            'height' => 57,
        );
        $post_data = json_encode($post_data);

        $header = array(
            "Content-Type: application/json; charset=UTF-8",
            "Content-Length: " . strlen($post_data),
            "Origin: https://id.sonyentertainmentnetwork.com",
        );

        $service = s('Common');
        $data = $service->curl($url, $header, $post_data, 'post');
        return json_decode($data, true);
    }

    public function getLoginAccessToken($valid_code, $challenge, $reflush = 0)
    {
        $redis = r('psn_redis');
        $redis_key = 'auth_info:login';
        $info = $redis->hGetAll($redis_key);
        if (!empty($info) && empty($reflush)) {
            return $info;
        }

        $url = "https://auth.api.sonyentertainmentnetwork.com/2.0/oauth/token";
        $header = array("Origin: https://id.sonyentertainmentnetwork.com");
        $post_data = array(
            'grant_type'       => 'captcha',
            'captcha_provider' => 'auth:simplecaptcha',
            'scope'            => 'oauth:authenticate',
            'valid_for'        => 'funwee@qq.com',
            'client_id'        => '71a7beb8-f21a-47d9-a604-2e71bee24fe0',
            'client_secret'    => 'xSk2YI8qJqZfeLQv',
            'challenge'        => $challenge,
            'response'         => $valid_code,
        );

        $post_data = http_build_query($post_data);
        $service = s('Common');
        $data = $service->curl($url, $header, $post_data, 'post');
        if (!empty($data)) {
            $info = json_decode($data, true);
            if (!empty($info['error'])) {
                return $this->setError($info['error_code'], $info['error']);
            }
            $redis->hMset($redis_key, $info);
            $redis->expire($redis_key, 3600);
        } else {
            $info = array();
        }
        return $info;
    }

    public function getNpsso()
    {
        $npsso = 'UXezp0I4G6ToYN39ueMRUQQYXcAZt2rCkdRBQoF6SSMHxAsP3EU5FiyPkiemmHh2';
        return $npsso;
        $redis = r('psn_redis');
        $redis_key = 'auth_info:login';
        $info = $redis->hGetAll($redis_key);
        $url = "https://auth.api.sonyentertainmentnetwork.com/2.0/ssocookie";
        $post_data = array(
            'authentication_type' => 'password',
            'username' => 'funwee@qq.com',
            'password' => 'hw2924920',
            'client_id' => '71a7beb8-f21a-47d9-a604-2e71bee24fe0',
        );

        $post_data = json_encode($post_data);
        $header = array(
            "Content-Type: application/json; charset=UTF-8",
            "Content-Length: " . strlen($post_data),
            "Origin: https://id.sonyentertainmentnetwork.com",
            "Authorization:{$info['token_type']} {$info['access_token']}",
        );

        $service = s('Common');
        $data = $service->curl($url, $header, $post_data, 'post');
        $data = json_decode($data, true);
        if (!empty($data['npsso'])) {
            $redis = r('psn_redis');
            $redis_key = 'auth_info:sso_cookie';
            $redis->hSet($redis_key, 'npsso', $data['npsso']);
        }
        return $data['npsso'];
    }

    public function getGrantCode()
    {
        $url = "https://auth.api.sonyentertainmentnetwork.com/2.0/oauth/authorize?client_id=ebee17ac-99fd-487c-9b1e-18ef50c39ab5&redirect_uri=com.playstation.PlayStationApp%3A%2F%2Fredirect&response_type=code&scope=kamaji%3Aget_account_hash%20kamaji%3Aactivity_feed_submit_feed_story%20kamaji%3Aactivity_feed_internal_feed_submit_story%20kamaji%3Aactivity_feed_get_news_feed%20kamaji%3Acommunities%20kamaji%3Agame_list%20kamaji%3Augc%3Adistributor%20oauth%3Amanage_device_usercodes%20psn%3Asceapp%20user%3Aaccount.profile.get%20user%3Aaccount.attributes.validate%20user%3Aaccount.settings.privacy.get%20kamaji%3Aactivity_feed_set_feed_privacy";
        $header = array(
            "Origin: https://id.sonyentertainmentnetwork.com",
        );
        $npsso = $this->getNpsso();
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }
        $sso_cookie = "npsso={$npsso}";
        $service = s('Common');
        $head = $service->curlHeader($url, $header, $sso_cookie);

        preg_match("/X-NP-GRANT-CODE:([^\r\n]*)/i", $head, $matches);
        $grant_code = trim($matches[1]);

        $data['head'] = explode("\r\n", $head);
        $data['sso_cookie'] = $sso_cookie;
        $data['grant_code'] = $grant_code;

        if (empty($grant_code)) {
            return $this->setError('get_grant_code_fail', json_encode($data));
        }
        return $data;
    }

    public function getApiAccessToken()
    {
        $redis = r('psn_redis');
        $redis_key = 'auth_info:api';
        $info = $redis->hGetAll($redis_key);
        if (!empty($info)) {
            return $info;
        }

        $url = "https://auth.api.sonyentertainmentnetwork.com/2.0/oauth/token";
        $header = array("Origin: https://id.sonyentertainmentnetwork.com");
        $scope = "kamaji:get_account_hash kamaji:activity_feed_submit_feed_story kamaji:activity_feed_internal_feed_submit_story kamaji:activity_feed_get_news_feed kamaji:communities kamaji:game_list kamaji:ugc:distributor oauth:manage_device_usercodes psn:sceapp user:account.profile.get user:account.attributes.validate user:account.settings.privacy.get kamaji:activity_feed_set_feed_privacy";
        $grant_info = $this->getGrantCode();

        if ($this->hasError()) {
            return $this->setError($this->getError());
        }
        $post_data = array(
            'grant_type' => 'authorization_code',
            'scope' => $scope,
            'redirect_uri' => 'com.playstation.PlayStationApp://redirect',
            'code' => $grant_info['grant_code'],
            'client_id' => 'ebee17ac-99fd-487c-9b1e-18ef50c39ab5',
            'client_secret' => 'e4Ru_s*LrL4_B2BD',
        );

        $post_data = http_build_query($post_data);
        $service = s('Common');
        $data = $service->curl($url, $header, $post_data, 'post', $grant_info['sso_cookie']);
        if (!empty($data)) {
            $info = json_decode($data, true);
            if (!empty($info['error'])) {
                return $this->setError($info['error_code'], $info['error']);
            }
            $redis->hMset($redis_key, $info);
            $redis->expire($redis_key, 3600);
        } else {
            $info = array();
        }
        return $info;
    }

    /**
    <div class="post">
    <a class="l" href="http://psnine.com/psnid/unistalling"><img src="http://photo.psnine.com/avatar/HP0102/CUSA06605_00-PREMIUMAVATAR037_3ED12F7996610452541B_l.png?x-oss-process=image/resize,w_50" width="50" height="50" /></a>
    <div class="ml64">
    <div class="content pb10">
    居然叫最后一次，好伤感。<br />再（也不）见。	</div>
    <div class="meta">
    <a href="http://psnine.com/psnid/unistalling" class="psnnode">unistalling</a>
    2年前	</div>
     */
    public function getPsnineTips($game_id, $trophy_id)
    {
        $start = (int)substr($game_id, 4, 5);
        $end = str_pad($trophy_id + 1, 3, 0, STR_PAD_LEFT);
        $id = (string)$start . (string)$end;

        $url = "http://psnine.com/trophy/{$id}";
        $service = s('Common');
        $response = $service->curl($url);

        $response =  preg_replace_callback('/<a href=\"(.*?)\"/i', function($matchs){
            return str_replace($matchs[1], '#', $matchs[0]);
        }, $response);
        $preg = '/<a class=\"l\"(.*?)<img src=\"(.*?)\" width/i';
        preg_match_all($preg, $response, $matches);
        $avatar_arr = $matches[2];
        unset($matches);
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
        return $list;
    }

    protected function getTrophyTipsFromDb($game_id, $trophy_id)
    {
        $db = pdo();
        $db->tableName = 'trophy_tips';
        $where['game_id'] = $game_id;
        $where['trophy_id'] = $trophy_id;
        $list = $db->findAll($where, 'nickname,avatar,content');

        return $list;
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

}