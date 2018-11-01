<?php
class DiscoveryInterface extends BaseInterface
{
    public function getTab()
    {
        $service = s('Goods');
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

        $service = s('Goods');
        $result = $service->getDiscoveryList($type, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
}