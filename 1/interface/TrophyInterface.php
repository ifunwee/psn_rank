<?php
class TrophyInterface extends BaseInterface
{
    /**
     * 获取用户游戏列表
     */
    public function getUserTrophyTitleList()
    {
        $psn_id = getParam('psn_id');
        $sort_type = getParam('sort_type');
        $page = getParam('page', 1);

        $service      = s('Trophy');
        $result = $service->getUserTrophyTitleList($psn_id, $sort_type, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function syncUserTrophyTitle()
    {
        $psn_id = getParam('psn_id');

        $service      = s('Trophy');
        $result = $service->syncUserTrophyTitle($psn_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }


}