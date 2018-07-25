<?php
class PromotionInterface extends BaseInterface
{
    /**
     * 最新优惠
     */
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

    /**
     * 会员独享
     */
    public function plus()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice', 'cn');
        $info = $service->getPromotionList('plus', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 折扣力度
     */
    public function discount()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice', 'cn');
        $info = $service->getPromotionList('discount', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 即将过期
     */
    public function expire()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice', 'cn');
        $info = $service->getPromotionList('expire', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 最佳口碑
     */
    public function best()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice', 'cn');
        $info = $service->getPromotionList('best', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    /**
     * 热门游戏
     */
    public function hot()
    {
        $page = getParam('page');

        $service      = s('GoodsPrice', 'cn');
        $info = $service->getPromotionList('hot', $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }
}