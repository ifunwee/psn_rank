<?php

class MiniProgramService extends BaseService
{
    private $app_id;
    private $app_secret;
    private $sesison_key;
    public function __construct()
    {
        parent::__construct();
        $this->app_id = c('mini_programma.app_id');
        $this->app_secret = c('mini_programma.app_secret');
    }

    /**
     * 解密数据
     *
     * @param $encrypt_data
     * @param $iv
     * @param $code
     *
     * @return array|mixed
     */
    public function decryptData($encrypt_data, $iv, $code, $type)
    {
        if (empty($encrypt_data)) {
            return $this->setError('param_encrypt_data_is_empty');
        }

        if (empty($iv)) {
            return $this->setError('param_iv_is_empty');
        }

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

        if (isset($response['errcode'])) {
            return $this->setError($response['errcode'], $response['errmsg']);
        }

        $this->sesison_key = $response['session_key'];
//        $this->sesison_key = 'tiihtNczf5v6AKRyjwEUhQ==';
        $data = $this->handleDecrypt($encrypt_data, $iv);

        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        switch ($type) {
            case 'info' :
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
            case 'share' :
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
        if (strlen($this->sesison_key) != 24) {
            return $this->setError('illegal_session_key', '非法的session_key');
        }
        $aes_key = base64_decode($this->sesison_key);

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
            $db->insert($data);
        } else {
            $data['update_time'] = time();
            $db->update($data, $where);
        }

        $redis = r('psn_redis');
        $redis_key = redis_key('account_info', $data['open_id']);
        $redis->hMset($redis_key, $data);
    }
}