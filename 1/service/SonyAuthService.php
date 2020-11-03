<?php
class SonyAuthService extends BaseService
{
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

        if ($service->hasError()) {
            return $this->setError($service->getError());
        }
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
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }
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
        $npsso = 'cN1Nf4hwEG6Kx1dCBfKjnPTGkuaYIkmLO5uFMFBZbNBf7S7guSVDkRs1ZSoEtoFy';
        return $npsso;
        $redis = r('psn_redis');
        $redis_key = 'auth_info:login';
        $info = $redis->hGetAll($redis_key);
        $url = "https://auth.api.sonyentertainmentnetwork.com/2.0/ssocookie";
        $post_data = array(
            'authentication_type' => 'password',
            'username' => 'funwee@qq.com',
            'password' => '1q2w3e4r',
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
        $head = $service->curlHeader($url, $header);
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }
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
        if (empty($info)) {
            $data = $this->rebuildApiAccessToken();
        } else {
            if (time() > (int)$info['expire_timestamp'] || 1==1) {
                $data = $this->refreshApiAccessToken($info['refresh_token']);
            } else {
                return $info;
            }
        }

        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        if (!empty($data)) {
            $info = json_decode($data, true);
            if (!empty($info['error'])) {
                return $this->setError($info['error_code'], $info['error']);
            }
            $info['expire_timestamp'] = time() + 3300;
            $redis->hMset($redis_key, $info);
        } else {
            $info = array();
        }

        return $info;
    }

    //重新生成token
    public function rebuildApiAccessToken()
    {
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
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        return $data;
    }

    //刷新token
    public function refreshApiAccessToken($refresh_token)
    {
        $url = "https://auth.api.sonyentertainmentnetwork.com/2.0/oauth/token";
        $header = array("Origin: https://id.sonyentertainmentnetwork.com");
        $scope = "kamaji:get_account_hash kamaji:activity_feed_submit_feed_story kamaji:activity_feed_internal_feed_submit_story kamaji:activity_feed_get_news_feed kamaji:communities kamaji:game_list kamaji:ugc:distributor oauth:manage_device_usercodes psn:sceapp user:account.profile.get user:account.attributes.validate user:account.settings.privacy.get kamaji:activity_feed_set_feed_privacy";

        $post_data = array(
            'grant_type' => 'refresh_token',
            'scope' => $scope,
            'refresh_token' => $refresh_token,
            'client_id' => 'ebee17ac-99fd-487c-9b1e-18ef50c39ab5',
            'client_secret' => 'e4Ru_s*LrL4_B2BD',
        );

        $post_data = http_build_query($post_data);
        $service = s('Common');
        $data = $service->curl($url, $header, $post_data, 'post');
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        return $data;
    }
}
