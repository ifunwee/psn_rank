<?php

class CommonService extends BaseService
{
    public $is_change_proxy = false;
    public $is_proxy = false;
    /**
     * curl请求
     * @param        $url
     * @param array  $header
     * @param string $post_data
     * @param string $method
     * @param string $cookie
     *
     * @return mixed
     */
    public function curl($url, $header = array(), $post_data = '', $method = 'get', $cookie = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
//        curl_setopt($ch, CURLOPT_SSLVERSION, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);//设置请求最多重定向的次数

        if (!empty($this->is_proxy)) {
            $proxy = $this->getProxy();
//            var_dump("{$proxy['ip']}:{$proxy['port']}");
            if ($this->hasError()) {
                return $this->setError($this->getError());
            }
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);
//            curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC); //代理认证模式

            curl_setopt($ch, CURLOPT_PROXY, $proxy['ip']); //代理服务器地址
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']); //代理服务器端口
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); //使用http代理模式
        }

        $output = curl_exec($ch);

//                $curl_info = curl_getinfo($ch);

        $error     = curl_error($ch);
        $errno     = curl_errno($ch);
        $curl_info = curl_getinfo($ch);

        if ($error) {
            Log::e("request_curl_exception code:{$errno} msg:{$error} url:{$url} debug_info:" . var_export($curl_info, true));
//            echo "request_curl_exception code:{$errno} msg:{$error} url:{$url} debug_info:" . var_export($curl_info, true);
            curl_close($ch);
            return $this->setError($errno, $error);
        }

        //        var_dump($errno, $error);exit;

        curl_close($ch);
        return $output;

    }

    /**
     * curl cookie
     * @param       $url
     * @param array $header
     *
     * @return mixed
     */
    public function curlCookie($url, $header = array())
    {
        // 初始化CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        // 获取头部信息
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($ch);
        curl_close($ch);
        // 解析http数据流
        list($head, $body) = explode("\r\n\r\n", $content);
        // 解析cookie
        preg_match("/set\-cookie:([^\r\n]*)/i", $head, $matches);
        $cookie = $matches[1];

        return $cookie;
    }

    /**
     * curl header
     * @param        $url
     * @param array  $header
     * @param string $cookie
     *
     * @return string
     */
    public function curlHeader($url, $header = array(), $cookie = '')
    {
        // 初始化CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        // 获取头部信息
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);//设置请求最多重定向的次数
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1); //TRUE 时追踪句柄的请求字符串，从 PHP 5.1.3 开始可用。这个很关键，就是允许你查看请求header
        echo curl_getinfo($ch, CURLINFO_HEADER_OUT); //官方文档描述是“发送请求的字符串”，其实就是请求的
        $content = curl_exec($ch);
        curl_close($ch);
        // 解析http数据流
        list($head, $body) = explode("\r\n\r\n", $content);

        if (empty($head)) {
            return '';
        }

        return $head;
    }

    /**
     * 将json中驼峰命名转下划线命名
     * 思路:
     * 小写和大写紧挨一起的地方,加上分隔符,然后全部转小写
     */
    function uncamelizeJson($json)
    {
        $data =  preg_replace_callback('/\"(.*?)\":(.*?)[\,|\[|\]|\{|\}]/', function($matchs){
            $lower = strtolower(preg_replace('/([a-z])([A-Z])/', "$1_$2", $matchs[1]));
            return str_replace($matchs[1], $lower, $matchs[0]);
        }, $json);

        return $data;
    }

    /**
     * 下划线转驼峰
     * 思路:
     * step1.原字符串转小写,原字符串中的分隔符用空格替换,在字符串开头加上分隔符
     * step2.将字符串中每个单词的首字母转换为大写,再去空格,去字符串首部附加的分隔符.
     */
    function camelize($uncamelized_words, $separator='_')
    {
        $uncamelized_words = $separator. str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator );
    }

    /**
     * 驼峰命名转下划线命名
     * 思路:
     * 小写和大写紧挨一起的地方,加上分隔符,然后全部转小写
     */
    function uncamelize($camel_caps, $separator='_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camel_caps));
    }

    /**
     * 过滤4位emoj表情
     * @param $message
     *
     * @return mixed
     */
    public function faceExec($message)
    {
        $message = preg_replace_callback('/[\xf0-\xf7].{3}/', function ($match) {
            return '';
        }, $message);

        return $message;
    }

    public function handlePsnImage($image, $width = 200, $height = 200, $type = 'image')
    {
        if (empty($image)) {
            return '';
        }

        switch ($type) {
            case 'image' :
                $domain = c('playstation_image_domain');
                break;
            case 'media' :
                $domain = c('playstation_media_domain');
                break;
            default :
                return $image;
        }

        if (!empty($image) && strpos($image, 'http') === false) {
            $handle_image = $domain . $image . "?imageView2/0/w/{$width}/h/{$height}";
        } else {
            $handle_image = $image;
        }

        return $handle_image;
    }

    public function getProxy()
    {
        $redis = r('psn_redis');
        $redis_key = "proxy:{$GLOBALS['X_G']['soa']['distinctRequestId']}";
        $proxy = $redis->hGetAll($redis_key);
        if (empty($proxy) || $this->is_change_proxy) {
//            $request = 'https://proxy.357.im/api/proxies/premium?protocol=http';
//            $request = 'https://proxy.357.im/api/proxies/stable?protocol=http';
            $request = 'https://proxy.357.im/api/proxies/common?protocol=http';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $request);
            $response =  curl_exec($ch);
            curl_close($ch);

            $response = json_decode($response, true);
            $proxy = $response['data'];

            if (empty($proxy['ip'])) {
                return $this->setError('get_proxy_ip_fail');
            }
            $redis->hMset($redis_key, $proxy);
            $redis->expire($redis_key, 86400);
            $this->is_change_proxy = false;
        }
        return $proxy;

    }
}