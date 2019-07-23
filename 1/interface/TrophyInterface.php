<?php
class TrophyInterface extends BaseInterface
{
    /**
     * 获取用户游戏奖杯列表
     */
    public function getUserTrophyTitleList()
    {
        $psn_id = getParam('psn_id');
        $sort_type = getParam('sort_type');
        $page = getParam('page', 1);

        $service      = s('TrophyTitle');
        $result = $service->getUserTrophyTitleList($psn_id, $sort_type, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    /**
     * 同步游戏奖杯列表
     */
    public function syncUserTrophyTitle()
    {
        $psn_id = getParam('psn_id');

        $service      = s('TrophyTitle');
        $result = $service->syncUserTrophyTitle($psn_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }


    public function getUserTrophyDetail()
    {
        $psn_id = getParam('psn_id');
        $np_communication_id = getParam('np_communication_id');

        $service      = s('TrophyDetail');
        $result = $service->getUserTrophyDetail($psn_id, $np_communication_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function syncUserTrophyDetail()
    {
        $psn_id = getParam('psn_id');
        $np_communication_id = getParam('np_communication_id');

        $service      = s('TrophyDetail');
        $result = $service->syncUserTrophyDetail($psn_id, $np_communication_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }


    public function getTrophyTips()
    {
        $np_communication_id = getParam('np_communication_id');
        $trophy_id = getParam('trophy_id');
        $page = getParam('page', 1);

        $service      = s('TrophyTips');
        $result = $service->getTrophyTips($np_communication_id, $trophy_id, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
}