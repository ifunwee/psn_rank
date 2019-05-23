<?php
class GameInterface extends BaseInterface
{

    public function search()
    {
        $name = getParam('name');
        $page = getParam('page', 1);

        $service      = s('Game');
        $info = $service->search($name, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    public function getTab()
    {
        $service = s('Game');
        $result = $service->getDiscoveryTab();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function getTabList()
    {
        $type = getParam('type');
        $page = getParam('page');

        $service = s('Game');
        $result = $service->getDiscoveryList($type, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
}