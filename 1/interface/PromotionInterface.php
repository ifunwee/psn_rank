<?php
class PromotionInterface extends BaseInterface
{
    public function recent()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice', 'cn');
        $info = $service->getPromotionList('recent', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }
}