<?php
class SonyAuthInterface extends BaseInterface
{
    /**
     * 获取登陆验证码
     */
    public function getCaptcha()
    {
        $service      = s('SonyAuth');
        $info = $service->getCaptcha();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取登陆access_token
     */
    public function getLoginAccessToken()
    {
        $valid_code = getParam('valid_code');
        $challenge = getParam('challenge');
        $reflush = getParam('reflush', 0);

        $service      = s('SonyAuth');
        $info = $service->getLoginAccessToken($valid_code, $challenge, $reflush);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取sso
     */
    public function getNpsso()
    {
        $service      = s('SonyAuth');
        $info = $service->getNpsso();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取grant_code
     */
    public function getGrantCode()
    {
        $service      = s('SonyAuth');
        $info = $service->getGrantCode();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取api access_token
     */
    public function getApiAccessToken()
    {
        $service      = s('SonyAuth');
        $info = $service->getApiAccessToken();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }
}