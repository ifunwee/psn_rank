<?php
class FollowInterface extends BaseInterface
{
    public function operate()
    {
        $open_id = getParam('open_id');
        $goods_id = getParam('goods_id');
        $action = getParam('action');

        $service      = s('Follow');
        $service->operate($open_id, $goods_id, $action);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess(null);
    }

    public function getMyFollowList()
    {
        $open_id = getParam('open_id');
        $page = getParam('page');

        $service      = s('Follow');
        $data = $service->getMyFollowList($open_id, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($data);
    }

    public function isFollow()
    {
        $open_id = getParam('open_id');
        $goods_id = getParam('goods_id');

        $service      = s('Follow');
        $data = $service->isFollow($open_id, $goods_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($data);
    }
}