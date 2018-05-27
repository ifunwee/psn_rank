<?php

class ProfileInterface extends BaseInterface
{

    /**
     * 获取用户psn资料信息
     */
    public function getUserInfo()
    {
        $psn_id = getParam('psn_id');
        $open_id = getParam('open_id');

        $service      = s('Profile');
        $info = $service->getUserInfo($psn_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        if (!empty($info)) {
            $service->bind($open_id, $psn_id);
            if ($service->hasError()) {
                $this->respondFailure($service->getError());
            }
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取用户游戏列表
     */
    public function getUserGameList()
    {
        $psn_id = getParam('psn_id');
        $refresh = getParam('refresh', 0);

        $service      = s('Profile', $refresh);
        $info = $service->getUserGameList($psn_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取用户游戏详情
     */
    public function getGameDetail()
    {
        $psn_id = getParam('psn_id');
        $game_id = getParam('game_id');

        $service      = s('Profile');
        $info = $service->getGameDetail($psn_id, $game_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取用户游戏进度
     */
    public function getGameProgress()
    {
        $psn_id = getParam('psn_id');
        $game_id = getParam('game_id');
        $refresh = getParam('refresh', 0);

        $service      = s('Profile', $refresh);
        $info = $service->getGameProgress($psn_id, $game_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 【废弃】
     * 获取用户游戏详情 相当于getUserGameInfo + getUserGameProgress
     */
    public function getUserGameDetail()
    {
        $psn_id = getParam('psn_id');
        $game_id = getParam('game_id');

        $service      = s('Profile');
        $info = $service->getUserGameDetail($psn_id, $game_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 【废弃】
     * 获取用户游戏基本信息
     */
    public function getUserGameInfo()
    {
        $psn_id = getParam('psn_id');
        $game_id = getParam('game_id');

        $service      = s('Profile');
        $info = $service->getUserGameInfo($psn_id, $game_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 【废弃】
     * 获取用户游戏进度
     */
    public function getUserGameProgress()
    {
        $psn_id = getParam('psn_id');
        $game_id = getParam('game_id');
        $version_id = getParam('version_id', 'default');

        $service      = s('Profile');
        $data = $service->getUserGameProgress($psn_id, $game_id, $version_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($data);
    }

    /**
     * 获取登陆验证码
     */
    public function getCaptcha()
    {
        $service      = s('Profile');
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

        $service      = s('Profile');
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
        $service      = s('Profile');
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
        $service      = s('Profile');
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
        $service      = s('Profile');
        $info = $service->getApiAccessToken();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    public function test()
    {
        $json = file_get_contents("php://input");
//        $json = json_decode($json, true);
        log::i($json);
    }
}
