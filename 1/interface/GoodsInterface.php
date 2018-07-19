<?php
class GoodsInterface extends BaseInterface
{
    public function detail()
    {
        $goods_id = getParam('goods_id');

        $service      = s('Goods');
        $info = $service->getGoodsInfo($goods_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }
}