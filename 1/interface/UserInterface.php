<?php
class UserInterface extends BaseInterface
{
    public function buildJWT()
    {
        $service = s('Common');
        $payload = array('user_id'=>'9ac50f047767e4292d8eeec5ec72292e');

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