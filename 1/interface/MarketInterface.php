<?php
class MarketInterface extends BaseInterface
{
    public function __construct()
    {
        parent::__construct();
        if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
            header('Access-Control-Allow-Methods: *');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 3600');
            exit;
        }
    }

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

}