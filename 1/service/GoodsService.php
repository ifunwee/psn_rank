<?php
class GoodsService extends BaseService
{
    public function getPromotionList($type, $page = 1, $limit = 20)
    {
        if (empty($type)) {
            return $this->setError('param_type_is_empty');
        }

        $list = array();
        switch ($type) {
            case 'recent':
                $where = "origin_price > 0";
                $sort = 'promo_start_date desc';
                $goods_list = $this->getGoodsListFromDb($where, '', $sort, $page, $limit);

                foreach ($goods_list as $goods) {
                    $info = array(
                        'goods_id' => $goods['goods_id'],
                        'name' => $goods['name'],
                        'cover_image' => $goods['cover_image'],
                        'promo_start_date' => $goods['promo_start_date'],
                        'promo_end_date' => $goods['promo_end_date'],
                        'origin_price' => strval($goods['origin_price'] / 100),
                        'sale_price' => strval($goods['sale_price'] / 100),
                        'plus_price' => strval($goods['plus_price'] / 100),
                        'discount' => $goods['sale_price'] ? number_format((1-$goods['sale_price']/$goods['origin_price']) * 100, 0) : '',
                        'plus_discount' => $goods['plus_price'] ? number_format((1-$goods['plus_price']/$goods['origin_price']) * 100, 0): '',
                    );

                    $list[] = $info;
                }
                break;
            default :
                return $this->setError('invalid_type');
        }
        return $list;
    }

    public function getGoodsInfo($goods_id)
    {
        if (empty($goods_id)) {
            return $this->setError('param_goods_id_is_empty');
        }
        $goods = $this->getGoodsInfoFromDb($goods_id);
        $info = array(
            'name' => $goods['name'],
            'cover_image' => $goods['cover_image'],
            'description' => $goods['description'],
            'rating_score' => $goods['rating_score'],
            'rating_total' => $goods['rating_total'],
            'publisher' => $goods['publisher'],
            'developer' => $goods['developer'],
            'release_date' => $goods['release_date'],
            'promo_start_date' => $goods['promo_start_date'],
            'promo_end_date' => $goods['promo_end_date'],
            'origin_price' => strval($goods['origin_price'] / 100),
            'sale_price' => strval($goods['sale_price'] / 100),
            'plus_price' => strval($goods['plus_price'] / 100),
            'discount' => $goods['sale_price'] ? number_format((1-$goods['sale_price']/$goods['origin_price']) * 100, 0) : '',
            'plus_discount' => $goods['plus_price'] ? number_format((1-$goods['plus_price']/$goods['origin_price']) * 100, 0): '',
            'preview' => $goods['preview'] ? json_decode($goods['preview']) : '',
            'screenshots' => $goods['screenshots'] ? json_decode($goods['screenshots']) : '',
        );

        return $info;
    }

    protected function getGoodsListFromDb($where = '', $field = '', $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? $field : '*';
        $sort = $sort ? $sort : 'id desc';
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'goods';
        $list = $db->findAll($where, $field, $sort, $limit_str);

        return $list;
    }

    protected function getGoodsInfoFromDb($goods_id)
    {
        $db = pdo();
        $db->tableName = 'goods';
        $where['goods_id'] = $goods_id;
        $info = $db->find($where);

        return $info;
    }
}