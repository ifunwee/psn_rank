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
        $refresh = getParam('refresh', 0);


        $service      = s('Profile', $refresh);
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
        $refresh = getParam('refresh', 0);


        $service      = s('Profile', $refresh);
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
     * 获取奖杯信息
     */
    public function getTrophyInfo()
    {
        $game_id = getParam('game_id');
        $trophy_id = getParam('trophy_id');

        $service      = s('Profile');
        $info = $service->getTrophyInfo($game_id, $trophy_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取奖杯贴士
     */
    public function getTrophyTips()
    {
        $game_id = getParam('game_id');
        $trophy_id = getParam('trophy_id');
        $page = getParam('page', 1);

        $service      = s('TrophyTips');
        $result = $service->getTrophyTips($game_id, $trophy_id, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    /**
     * 获取psn_id
     */
    public function getPsnId()
    {
        $open_id = getParam('open_id');

        $service      = s('Profile');
        $result = $service->getPsnId($open_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
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



    public function getTrophyInfoByNptitleId()
    {
        $np_title_id = getParam('np_title_id');
        $service      = s('Profile');
        $info = $service->getTrophyInfoByNptitleId($np_title_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 获取用户psn资料信息
     */
    public function getPsnInfo()
    {
        $psn_id = getParam('psn_id');


        $service      = s('Profile');
        $info = $service->getPsnInfo($psn_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    public function syncPsnInfo()
    {
        $psn_id = getParam('psn_id');

        $service      = s('Profile');
        $info = $service->syncPsnInfo($psn_id);

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
