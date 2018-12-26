<?php
require_once(X_PATH . '/class/ResourceStorage/Qiniu.php');

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2017/2/21
 * Time: 下午6:59
 */
class QiniuService extends BaseService
{
    private $domain = '/%s';
    private $url    = 'http://api.qiniu.com/status/get/prefop?id=%s';

    /**
     * 多文件压缩
     */
    public function zipFile(array $fileUrls, $bucket = 'miscimg01', $pipeline = 'transcoding')
    {
        if (empty($fileUrls)) {
            return false;
        }
        //先将key写死压缩，隐藏动态处理key代码
//        $key       = parse_url($fileUrls[0]);
//        $key       = trim($key['path'], '/');
        $key       = c('default_pic');
        $accessKey = c('qiniu.accessKey');
        $secretKey = c('qiniu.secretKey');

        $qiniu  = new Qiniu($accessKey, $secretKey, $bucket);
        $saveAs = time() . '.zip';
        $data   = array(
            'key'       => $key,
            'ops'       => $this->getFops($fileUrls, $bucket, $saveAs),
            'notifyUrl' => c('zip_callback_domain') . '/v1/Callback/qiNiuZipNotify',
            'pipeline'  => $pipeline,
        );
        $result = $qiniu->pfop($data);
        if (!empty($result[0]['error'])) {
            Log::n("###zipPacketFailed###" . var_export($result[0]['error'], true));
        }
        if ($result[0]['persistentId']) {
            Log::i("###zipInfo###persistentId:" . $result[0]['persistentId']);
        }
//        $this->createCurl($persistentId[0]['persistentId']);
        return array(
            'url'          => sprintf($this->domain, $saveAs),
            'persistentId' => empty($result[0]['persistentId']) ? '' : $result[0]['persistentId'],
        );
    }

    public function getFops($fileUrls = array(), $bucket, $saveFileName)
    {
        $fops = "mkzip/2";

        foreach ($fileUrls as $fileUrl) {
            $fops = $fops . "/url/" . $this->base64_urlSafeEncode($fileUrl);
        }

        $fops .= '|saveas/' . $this->base64_urlSafeEncode("$bucket:$saveFileName");

        return $fops;
    }

    public function uploadFile($fileData, $saveName = '', $bucket = '')
    {
        $accessKey = c('qiniu.accessKey');
        $secretKey = c('qiniu.secretKey');

        $qiniu     = new Qiniu($accessKey, $secretKey, $bucket);
        $data = base64_decode($fileData);
        $result = $qiniu->uploadStream($data, $saveName);
        if ($result[1]) {
            return $this->setError($result[1]->Code, $result[1]->Err);
        }

//        return $result[0]['key'];
        return sprintf($this->domain, $result[0]['key']);
    }

    function base64_urlSafeEncode($data)
    {
        $find    = array('+', '/');
        $replace = array('-', '_');
        return str_replace($find, $replace, base64_encode($data));
    }

    public function createCurl($persistentId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf($this->url, $persistentId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
    }

    public function notifyCallback () {
        $raw = file_get_contents('php://input');
        $data = urldecode($raw);
        if (empty($data)) {
            return ;
        } else {
            $data = json_decode($data, true);
        }
        $service = s('CacheHornor');
        $service->setZipStatus($data['id'], $data['code']);
        if (intval($data['code']) !== 0) {
            return $this->setError($data['code'], var_export($data['item'], true));
        }
    }

    public function snapShotCallback () {
        $raw = file_get_contents('php://input');
        $data = urldecode($raw);
        if (empty($data)) {
            return ;
        } else {
            $data = json_decode($data, true);
        }
        if (!empty($data['code'])) {
            Log::w("###snapShotCallbackFailed###ID" . $data['id']);
            exit;
        }
        $redis = r('comb_info_redis');
        $redis_key = sprintf(c('redis_key.snap_shot_pipeline_id'), $data['id']);
        $host_uuid = $redis->get($redis_key);
        if (!empty($data['items'][0]['key'])) {
            $service = s('HostCombinedInfo');
            $service->hSetItem($host_uuid, 'live_img_url', c('snap_shop_domain') . $data['items'][0]['key']);
        }
    }
}