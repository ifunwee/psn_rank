<?php
class GoodsInterface extends BaseInterface
{
    public function detail()
    {
        $goods_id = getParam('goods_id');

        $service      = s('Goods', 'cn');
        $info = $service->detail($goods_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }
}