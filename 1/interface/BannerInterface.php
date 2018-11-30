<?php
class BannerInterface extends BaseInterface
{
    public function getList()
    {
        $service      = s('Banner');
        $result = $service->getList();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
}