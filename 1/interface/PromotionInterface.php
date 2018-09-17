<?php
class PromotionInterface extends BaseInterface
{
    /**
     * 最新优惠
     */
    public function recent()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice');
        $result = $service->getPromotionList('recent', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    /**
     * 会员独享
     */
    public function plus()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice');
        $result = $service->getPromotionList('plus', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    /**
     * 折扣力度
     */
    public function discount()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice');
        $result = $service->getPromotionList('discount', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    /**
     * 即将过期
     */
    public function expire()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice');
        $result = $service->getPromotionList('expire', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    /**
     * 最佳口碑
     */
    public function best()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice');
        $result = $service->getPromotionList('best', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    /**
     * 热门游戏
     */
    public function hot()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice');
        $result = $service->getPromotionList('hot', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
    
    public function getTab()
    {
        $service = s('GoodsPrice');
        $result = $service->getPromotionTab();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function getTabList()
    {
        $type = getParam('type');
        $page = getParam('page');

        $service = s('GoodsPrice');
        $result = $service->getPromotionList($type, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
}