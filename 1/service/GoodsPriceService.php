<?php
class GoodsPriceService extends BaseService
{
    private $suffix;
    public function __construct($lang = 'cn')
    {
        parent::__construct();
        switch ($lang) {
            case 'en' :
                $this->suffix = '';
                break;
            case 'cn' :
                $this->suffix = '_cn';
                break;
        }
    }

    public function getPromotionList($type, $page = 1, $limit = 20)
    {
        if (empty($type)) {
            return $this->setError('param_type_is_empty');
        }

        $service = s('Goods');
        switch ($type) {
            case 'recent':
                $where = "discount > 0 or plus_discount > 0";
                $sort = 'start_date desc, update_time desc';
                $price_list = $this->getGoodsPriceListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'plus':
                $where = "discount = 0 and plus_discount > 0";
                $sort = 'plus_discount desc, update_time desc';
                $price_list = $this->getGoodsPriceListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'discount':
                $where = "discount > 0";
                $sort = 'discount desc, update_time desc';
                $price_list = $this->getGoodsPriceListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'expire':
                $where = "discount > 0 or plus_discount > 0 and end_date > 0";
                $sort = 'end_date asc, update_time desc';
                $price_list = $this->getGoodsPriceListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'best':
                $where = "discount > 0 or plus_discount > 0";
                $sort = 'rating_score desc, b.update_time desc';
                $goods_list = $service->getGoodsListWithPriceFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsPrice($goods_list);
                break;
            case 'hot':
                $where = "discount > 0 or plus_discount > 0";
                $sort = 'rating_total desc, update_time desc';
                $goods_list = $service->getGoodsListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsPrice($goods_list);
                break;
            default :
                return $this->setError('invalid_type');
        }
        return $list;
    }

    public function getGoodsPrice($goods_id, $field = array())
    {
        if (empty($goods_id)) {
            return $this->setError('param_goods_id_is_empty');
        }
        $info = $this->getGoodsPriceFromDb($goods_id, $field);
        $data = array();
        if (is_array($goods_id)) {
            foreach ($info as $goods_id => $price) {
                $data[$goods_id] = array(
                    'promo_date' => array(
                        'start_date' => $price['start_date'] ? : '',
                        'end_date' => $price['end_date'] ? : '',
                    ),
                    'non_plus_user' => array(
                        'origin_price' => number_format($price['origin_price'] / 100, 2),
                        'sale_price' => number_format($price['sale_price'] / 100, 2),
                        'discount' => $price['discount'],
                        'price_unit' => c('price_unit'),
                    ),
                    'plus_user' => array(
                        'origin_price' => number_format($price['plus_origin_price'] / 100, 2),
                        'sale_price' => number_format($price['plus_sale_price'] / 100, 2),
                        'discount' => $price['plus_discount'],
                        'price_unit' => c('price_unit'),
                    ),
                );
            }
        } else {
            $data = array(
                'promo_date' => array(
                    'start_date' => $info['start_date'] ? : '',
                    'end_date' => $info['end_date'] ? : '',
                ),
                'non_plus_user' => array(
                    'origin_price' => number_format($info['origin_price'] / 100, 2),
                    'sale_price' => number_format($info['sale_price'] / 100, 2),
                    'discount' => $info['discount'],
                    'price_unit' => c('price_unit'),
                ),
                'plus_user' => array(
                    'origin_price' => number_format($info['plus_origin_price'] / 100, 2),
                    'sale_price' => number_format($info['plus_sale_price'] / 100, 2),
                    'discount' => $info['plus_discount'],
                    'price_unit' => c('price_unit'),
                ),
            );
        }

        return $data;
    }

    /**
     * 获取商品价格 （数据库）
     * @param       $goods_id
     * @param array $field
     *
     * @return array
     */
    protected function getGoodsPriceFromDb($goods_id, $field = array())
    {
        $db = pdo();
        $db->tableName = 'goods_price';
        $field = $field ? implode(',', $field) : '*';
        $result = array();
        if (is_array($goods_id)) {
            $goods_id = array_unique($goods_id);
            $goods_id_str = implode("','", $goods_id);
            $where = "goods_id in ('{$goods_id_str}')";
            $list = $db->findAll($where, $field);
            foreach ($list as $goods) {
                $result[$goods['goods_id']] = $goods;
            }
        } else {
            $where = "goods_id = '{$goods_id}'";
            $result = $db->find($where, $field);
        }

        return $result;
    }

    /**
     * 获取商品价格排序列表 （数据库）
     * @param string $where
     * @param array  $field
     * @param string $sort
     * @param int    $page
     * @param int    $limit
     *
     * @return mixed
     */
    protected function getGoodsPriceListFromDb($where = '', $field = array(), $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? implode(',', $field) : '*';
        $sort = $sort ? $sort : 'id desc';
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'goods_price';
        $list = $db->findAll($where, $field, $sort, $limit_str);

        return $list;
    }

    private function completeGoodsInfo($price_list)
    {
        $list = array();
        if (empty($price_list)) {
            return $list;
        }
        $goods_id_arr = array_column($price_list, 'goods_id');
        $service = s('goods');
        $goods_info = $service->getGoodsInfo($goods_id_arr);
        foreach ($price_list as $price) {
            //不显示优惠已过期的商品
            if (!empty($price['end_date']) && time() > $price['end_date']) {
                continue;
            }
            $info = array(
                'goods_id' => $price['goods_id'],
                'name' => $goods_info[$price['goods_id']]['name'.$this->suffix],
                'cover_image' => $goods_info[$price['goods_id']]['cover_image'.$this->suffix],
                'rating_score' => $goods_info[$price['goods_id']]['rating_score'],
                'rating_total' => $goods_info[$price['goods_id']]['rating_total'],
                'price' => array(
                    'promo_date' => array(
                        'start_date' => $price['start_date'] ? : '',
                        'end_date' => $price['end_date'] ? : '',
                    ),
                    'non_plus_user' => array(
                        'origin_price' => number_format($price['origin_price'] / 100, 2),
                        'sale_price' => number_format($price['sale_price'] / 100, 2),
                        'discount' => $price['discount'],
                        'price_unit' => c('price_unit'),
                    ),
                    'plus_user' => array(
                        'origin_price' => number_format($price['plus_origin_price'] / 100, 2),
                        'sale_price' => number_format($price['plus_sale_price'] / 100, 2),
                        'discount' => $price['plus_discount'],
                        'price_unit' => c('price_unit'),
                    ),
                ),
            );

            $list[] = $info;
        }

        return $list;
    }

    private function completeGoodsPrice($goods_list)
    {
        $list = array();
        if (empty($goods_list)) {
            return $list;
        }
        $goods_id_arr = array_column($goods_list, 'goods_id');
        $goods_price = $this->getGoodsPrice($goods_id_arr);

        foreach ($goods_list as $goods) {
//            $discount = $goods_price[$goods['goods_id']]['non_plus_user']['discount'];
//            $plus_discount = $goods_price[$goods['goods_id']]['plus_user']['discount'];
//            $end_date = $goods_price[$goods['goods_id']]['promo_date']['end_date'];
//            if (empty($discount) || empty($plus_discount) || time() > $end_date) {
//                continue;
//            }
            $info = array(
                'goods_id' => $goods['goods_id'],
                'name' => $goods['name'.$this->suffix],
                'cover_image' => $goods['cover_image'.$this->suffix],
                'rating_score' => $goods['rating_score'],
                'rating_total' => $goods['rating_total'],
                'price' => $goods_price[$goods['goods_id']],
            );
            $list[] = $info;
        }

        return $list;
    }

}