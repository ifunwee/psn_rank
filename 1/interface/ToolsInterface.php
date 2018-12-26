<?php
class ToolsInterface extends BaseInterface
{
    public function test()
    {
        $open_id = $this->randOpenId();
        $redis = r('psn_redis');
        $redis_key = redis_key('gold_helper');
//        $redis->zAdd($redis_key, time(), $open_id);
//        $length = $redis->zCard($redis_key);
        $list = $redis->zRange($redis_key, 0, -1);
        var_dump($list);exit;
    }

    //随机生成openid
    function randOpenId() {
        $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-';
        $len = strlen($str);
        $id = 'oB4nYj';
        for ($i = 0; $i < 10; $i++) {
            $id .= $str[rand(0, $len - 1)];
        }
        return $id;
    }

    public function help()
    {
//        $redis = r('psn_redis');
//        $redis_key = redis_key('gold_helper');
//        $list = $redis->zRange($redis_key, 0 , -1);

        $header = array(
            "Host: cmb-gold-redpacket.weijuju.com",
            "Accept: application/json, text/javascript, */*; q=0.01",
            "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 12_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/16B92 MicroMessenger/6.7.3(0x16070321) NetType/WIFI Language/zh_CN",
        );
        $post_data = array(
            'shareopenid' => 'mT53LgLlX6lHu3CKJMiml2yprK/wjKym6PaEY7LCkLU=',
        );


        $post_data = http_build_query($post_data);
        $url = 'https://xiaozhao.wx.cmbchina.com/PCS2012/OAuthHandler.aspx?req=wx&MID=huaer&AuthType=snsapi_userinfo&Sub=y&Bind=y&CbUrl=https://cmb-gold-redpacket.weijuju.com/mobile/index?cmbtoken=Tqp9QJkJClk1P8CuyHtgnoZVr1uIWd&code=021KsPyt18oCSc0v92yt13YVyt1KsPyc&state=1';
        /** @var  $service  CommonService */
        $service = s('Common');
        $data = $service->curl($url, array(), '', 'get');
        var_dump($data);exit;
    }
}