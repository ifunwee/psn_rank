<?php
class UserInterface extends BaseInterface
{
    public function buildJWT()
    {
        $service = s('Common');
        $payload = array('user_id'=>'a6f9626281f711947470422b5d0f9133');

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