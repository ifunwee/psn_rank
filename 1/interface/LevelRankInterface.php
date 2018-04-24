<?php
class LevelRankInterface extends BaseInterface
{
    /**
     * 参与排行
     */
    public function joinRank()
    {
        $group_id = getParam('group_id');
        $open_id = getParam('open_id');

        $service      = s('LevelRank');
        $service->joinRank($group_id, $open_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess(null);
    }

    /**
     * 获取排行
     */
    public function getRank()
    {
        $group_id = getParam('group_id');
        $open_id = getParam('open_id');

        $service      = s('LevelRank');
        $data = $service->getRank($group_id, $open_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($data);
    }
}