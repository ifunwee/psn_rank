<?php
class MarketService extends BaseService
{
    /**
     * 获取贷超商品
     */
    public function getGoodsList($page)
    {
        $db = pdo('loan_db');
        $db->tableName = 'goods';
        $list = $db->findAll('status = 1', '*', 'sort desc');

        if (empty($list)) {
            return array();
        }

        foreach ($list as &$item) {
            if ($item['amount_min'] >= 10000) {
                $item['amount_min'] = round($item['amount_min']/10000, 2) . '万';
            }

            if ($item['amount_max'] >= 10000) {
                $item['amount_max'] = round($item['amount_max']/10000, 2) . '万';
            }
        }
        return $list;
    }

    /**
     * 发送短信验证码
     */
    public function sendCode($mobile)
    {
        $rand = mt_rand(100000, 999999);
        $redis = r('psn_redis');
        $reg_code_key = redis_key('loan_reg_code', $mobile);
        $redis->set($reg_code_key, $rand, 300);

        $content = "操作验证码: {$rand}, 5分钟内有效。";

        $service = s('ChuanglanSms');
        $result = $service->sendSMS($mobile, $content);
        return null;

        if(!is_null(json_decode($result))){

            $output=json_decode($result,true);

            if(isset($output['code'])  && $output['code']=='0'){
                return null;
            }else{
                return $this->setError('send_sms_fail', $output['errorMsg']);
            }
        }else{
            log::e('send_sms_error' . json_encode($result));
            return $this->setError('unknow_error');
        }
    }

    public function regMobile($mobile, $code, $channel_id)
    {
        if (empty($mobile)) {
            return $this->setError('param_mobile_is_empty', '手机号不能为空');
        }

        if (empty($code)) {
            return $this->setError('param_code_is_empty', '验证码不能为空');
        }


        $redis = r('psn_redis');
        $reg_code_key = redis_key('loan_reg_code', $mobile);
        $check_code = $redis->get($reg_code_key);
//        if ($code != $check_code) {
//            return $this->setError('code_is_invalid', '验证码错误或已失效');
//        } else {
//            $redis->expire($reg_code_key, 0);
//        }

        $db = pdo('loan_db');
        $db->tableName = 'account';

        $where['mobile'] = $mobile;
        $info = $db->find($where);

        if (!empty($info)) {
            return $this->setError('mobile_already_reg', '该手机号已注册过');
        }

        $data = array(
            'mobile' => $mobile,
            'channel_id' => $channel_id,
            'create_time' => time(),
        );

        $db->insert($data);
    }
}