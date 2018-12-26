<?php
class MarketInterface extends BaseInterface
{
    public function getGoodsList()
    {
        $page = getParam('page');

        $service      = s('Market');
        $data = $service->getGoodsList($page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($data, array('image_domain' => c('image_domain')));
    }

}