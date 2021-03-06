<?php
class GoodsInterface extends BaseInterface
{
    public function detail()
    {
        $goods_id = getParam('goods_id');
        $open_id = getParam('open_id');

        $service      = s('Goods', 'cn');
        $info = $service->detail($goods_id, $open_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    public function search()
    {
        $name = getParam('name');
        $page = getParam('page', 1);

        $service      = s('Goods', 'cn');
        $info = $service->search($name, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    public function dlc()
    {
        $service      = s('Goods', 'cn');
        $list = $service->getDlcList();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($list);
    }

    public function getAdditionList()
    {
        $np_title_id = getParam('np_title_id');

        $service      = s('Goods', 'cn');
        $list = $service->getAdditionList($np_title_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($list);
    }

    public function getAdditionDetail()
    {
        $goods_id = getParam('goods_id');

        $service      = s('Goods', 'cn');
        $result = $service->getAdditionDetail($goods_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
}