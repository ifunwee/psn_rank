<?php

class MiniProgramService extends BaseService
{
    private $app_id;
    private $app_secret;
    private $session_key;
    private $type;

    public function __construct($type = null)
    {
        parent::__construct();
        $appcode = b('appcode') ? : 2;
        $type && $appcode = $type;

        switch ((int)$appcode) {
            case 1 : $this->type = 'trophy'; break;
            case 2 : $this->type = 'price'; break;
        }

        $this->app_id = c("mini_program.{$this->type}.app_id");
        $this->app_secret = c("mini_program.{$this->type}.app_secret");
    }

    /**
     * 解密数据
     *
     * @param $type
     * @param $code
     * @param $encrypt_data
     * @param $iv
     *
     * @return array|mixed
     */
    public function decryptData($type, $code, $encrypt_data = '', $iv = '')
    {
        if (empty($code)) {
            return $this->setError('param_code_is_empty');
        }

        if (empty($type)) {
            return $this->setError('param_type_is_empty');
        }

        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->app_id}&secret={$this->app_secret}&js_code={$code}&grant_type=authorization_code";
        $service = s('Common');
        $response = $service->curl($url);
        $response = json_decode($response, true);

        if (!empty($response['errcode'])) {
            return $this->setError($response['errcode'], $response['errmsg']);
        }

        $data = array();
        switch ($type) {
            case 'info' :
                $this->session_key = $response['session_key'];
//                $this->session_key = 'tiihtNczf5v6AKRyjwEUhQ==';
                $data = $this->handleDecrypt($encrypt_data, $iv);

                if ($this->hasError()) {
                    return $this->setError($this->getError());
                }
                $data['nick_name'] = s('Common')->faceExec($data['nick_name']);
                $info = array(
                    'open_id' => $data['open_id'],
                    'nick_name' => $data['nick_name'],
                    'gender' => $data['gender'],
                    'city' => $data['city'],
                    'province' => $data['province'],
                    'country' => $data['country'],
                    'avatar_url' => $data['avatar_url'],
                    'union_id' => $data['union_id'],
                );
                $this->saveWechatUserInfo($info);
                if ($this->hasError()) {
                    return $this->setError($this->getError());
                }
                break;
            case 'auth' :
                $service = s('Profile');
                $psn_id = $service->getPsnId($response['openid']);
                $data['open_id'] = $response['openid'];
                $data['union_id'] = json_encode($response);
                $data['psn_id'] = $psn_id;

                break;
            case 'share':
                $this->session_key = $response['session_key'];
                $data = $this->handleDecrypt($encrypt_data, $iv);
                break;
            default:
                break;
        }

        return $data;
    }

    /**
     * 检验数据的真实性，并且获取解密后的明文.
     *
     * @param $encryptedData string 加密的用户数据
     * @param $iv            string 与用户数据一同返回的初始向量
     *
     */
    public function handleDecrypt($encrypt_data, $iv)
    {
        if (strlen($this->session_key) != 24) {
            return $this->setError('illegal_session_key', '非法的session_key');
        }
        $aes_key = base64_decode($this->session_key);

        if (strlen($iv) != 24) {
            return $this->setError('illegal_iv', '非法的iv');
        }
        $aes_iv = base64_decode($iv);

        $aes_cipher = base64_decode($encrypt_data);

        $result = openssl_decrypt($aes_cipher, "AES-128-CBC", $aes_key, 1, $aes_iv);

        $service = s('Common');
        $result = $service->uncamelizeJson($result);

        $data = json_decode($result, true);

        if (empty($data)) {
            return $this->setError('illegal_buffer', '解密后的buffer为空');
        }

        if ($data['watermark']['appid'] != $this->app_id) {
            return $this->setError('illegal_watermark', '解密后的buffer水印不匹配');
        }

        return $data;
    }

    /**
     * 保存微信用户基础信息
     * @param $data
     *
     * @return array
     */
    public function saveWechatUserInfo($data)
    {
        if (empty($data['open_id'])) {
            return $this->setError('param_open_id_is_empty');
        }

        $db = pdo();
        $db->tableName = 'account';
        $where['open_id'] = $data['open_id'];
        $info = $db->find($where);

        if (empty($info)) {
            $data['create_time'] = time();
            $data['appcode'] = b('appcode');
            $db->insert($data);
        } else {
            $data['update_time'] = time();
            $db->update($data, $where);
        }

        $redis = r('psn_redis');
        $redis_key = redis_key('account_info', $data['open_id']);
        $redis->hMset($redis_key, $data);
    }

    public function getAccessToken()
    {
        $redis = r('psn_redis');
        $access_token_key = redis_key("wechat_access_token", $this->type);
        $access_token = $redis->get($access_token_key);
        if (empty($access_token)) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->app_id}&secret={$this->app_secret}";
            $service = s('Common');
            $response = $service->curl($url);
            $response = json_decode($response, true);
            if (!empty($response['errcode'])) {
                return $this->setError($response['errcode'], $response['errmsg']);
            }
            $redis->set($access_token_key, $response['access_token'], $response['expires_in']);
            $access_token = $response['access_token'];
        }

        return $access_token;
    }

    public function collectFormId($open_id, $form_id)
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('collect_form_id', $open_id);
        $count = $redis->lLen($redis_key);
        if ($count > 10) {
            $redis->rPop($redis_key);
        }
        $data = $form_id . '_' . time();
        $redis->lpush($redis_key, $data);
    }

    public function getFormId($open_id)
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('collect_form_id', $open_id);
        $form_id_str = $redis->rPop($redis_key);

        if (empty($form_id_str)) {
            return $this->setError('unavailable_form_id', '没有可用的推送凭证');
        }

        while ($form_id_str) {
            $index = strrpos($form_id_str, '_');
            $form_id = substr($form_id_str, 0, $index);
            $create_time = substr($form_id_str, $index + 1);

            if (time() - $create_time < 86400 * 7) {
                $data = array(
                    'form_id' => $form_id,
                    'create_time' => $create_time,
                );

                return $data;
            }
            $form_id_str = $redis->rPop($redis_key);
            if (empty($form_id_str)) {
                return $this->setError('unavailable_form_id', '没有可用的推送凭证');
            }
        }
    }

    public function sendMessage($data)
    {
        $access_token = $this->getAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token={$access_token}";
        $service = s('Common');

        $header = array(
            "Content-Type: application/json; charset=UTF-8",
            "Content-Length: " . strlen($data),
        );

        $response = $service->curl($url, $header, $data, 'post');
        $response = json_decode($response, true);
        if (!empty($response['errcode'])) {
            return $this->setError($response['errcode'], $response['errmsg']);
        }

        return $response;
    }

    public function imgSecCheck($media)
    {
        $access_token = $this->getAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }
        $url = "https://api.weixin.qq.com/wxa/img_sec_check?access_token={$access_token}";
        $service = s('Common');
        $data = array('media' => new CURLFile($media));

        $response = $service->curl($url, array(), $data, 'post');

        $response = json_decode($response, true);
        if ((int)$response['errcode'] !== 0) {
            return $this->setError($response['errcode'], $response['errmsg']);
        }

        return true;
    }

    public function msgSecCheck($content)
    {
        $access_token = $this->getAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }
        $url = "https://api.weixin.qq.com/wxa/msg_sec_check?access_token={$access_token}";
        $service = s('Common');

        $data['content'] = $content;
        $json = json_encode($data, 256);

        $header = array(
            "Content-Type: application/json; charset=UTF-8",
            "Content-Length: " . strlen($json),
        );

        $response = $service->curl($url, $header, $json, 'post');
        $response = json_decode($response, true);
        if ((int)$response['errcode'] !== 0) {
            return $this->setError($response['errcode'], $response['errmsg']);
        }

        return true;
    }
}