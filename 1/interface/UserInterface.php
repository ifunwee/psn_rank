<?php
class UserInterface extends BaseInterface
{
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