<?php
class ToolsInterface extends BaseInterface
{

    public function upload()
    {
        $service      = s('Tools');
        $business_type = getParam('business_type');
        $result = $service->upload('', $business_type);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function beforeInterface()
    {
        parent::beforeInterface();
        s('Common')->allowCORS();
    }

    public function buildJWT()
    {
        $service = s('Common');
        $payload = array('sub'=>'1234567890','name'=>'John Doe','iat'=>1516239022);

        $result = $service->buildJWT($payload);
        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function parseJWT()
    {
        $token = getParam('token');
        $service = s('Common');
        $result = $service->parseJWT($token);
        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
}