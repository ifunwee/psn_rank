<?php
class MarketInterface extends BaseInterface
{
    public function getGoodsList()
    {
        $page = getParam('page');

        $service      = s('Market');
        $data = $service->getGoodsList($page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($data, array('image_domain' => c('image_domain')));
    }

    public function sendCode()
    {
        $mobile = getParam('mobile');

        $service      = s('Market');
        $service->sendCode($mobile);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess(null);
    }

    public function regMobile()
    {
        $mobile = getParam('mobile');
        $code = getParam('code');
        $channel_id = getParam('channel_id', 0);

        $service      = s('Market');
        $service-> regMobile($mobile, $code, $channel_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess(null);
    }

    public function getAccessStatus()
    {
        $data = array(
            'status' => 0
        );

        $this->respondSuccess($data);

    }

    public function beforeInterface()
    {
        parent::beforeInterface();
        s('Common')->allowCORS();
    }
}