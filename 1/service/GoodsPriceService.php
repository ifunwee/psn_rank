<?php
class GoodsPriceService extends BaseService
{
    private $suffix = '_cn';
    private $promotion_type = array(
        'recent',     //最新优惠
        'hot',        //热门游戏
        'plus',       //会员独享
        'expire',     //即将过期
        'discount',   //折扣力度
        'best',       //最佳口碑
    );

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

    public function getPromotionTab()
    {
        $data = array(
            array(
                'title' => '最新优惠',
                'type' => 'recent',
            ),
            array(
                'title' => '热门游戏',
                'type' => 'hot',
            ),
            array(
                'title' => '会员独享',
                'type' => 'plus',
            ),
            array(
                'title' => '即将过期',
                'type' => 'expire',
            ),
            array(
                'title' => '折扣力度',
                'type' => 'discount',
            ),
            array(
                'title' => '最佳口碑',
                'type' => 'best',
            ),
        );

        return $data;
    }

    public function getPromotionList($type, $page = 1, $limit = 20)
    {
        if (empty($type)) {
            return $this->setError('param_type_is_empty');
        }

        if (!in_array($type, $this->promotion_type)) {
            return $this->setError('invalid_promotion_type');
        }

        switch ($type) {
            case 'recent':
                $where = "(discount > 0 or plus_discount > 0) and IF(end_date > 0,UNIX_TIMESTAMP() < end_date, 1=1)";
                $sort = 'start_date desc, id desc';
                $price_list = $this->getGoodsPriceListFromDb($where, '', $sort, $page, $limit);

                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'plus':
                $where = "discount = 0 and plus_discount > 0 and IF(end_date > 0,UNIX_TIMESTAMP() < end_date, 1=1)";
                $sort = 'plus_discount desc, id desc';
                $price_list = $this->getGoodsPriceListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'discount':
                $where = "discount > 0 and IF(end_date > 0,UNIX_TIMESTAMP() < end_date, 1=1)";
                $sort = 'discount desc, id desc';
                $price_list = $this->getGoodsPriceListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'expire':
                $where = "(discount > 0 or plus_discount > 0) and end_date > 0 and UNIX_TIMESTAMP() < end_date";
                $sort = 'end_date asc, id desc';
                $price_list = $this->getGoodsPriceListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'best':
                $where = "rating_total > 500 and (discount > 0 or plus_discount > 0) and IF(end_date > 0,UNIX_TIMESTAMP() < end_date, 1=1)";
                $sort = 'rating_score desc, a.id desc';
                $price_list = $this->getGoodsPriceListWithInfoFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
                break;
            case 'hot':
                $where = "rating_total > 100 and (discount > 0 or plus_discount > 0) and IF(end_date > 0,UNIX_TIMESTAMP() < end_date, 1=1)";
                $sort = 'rating_total desc, a.id desc';
                $price_list = $this->getGoodsPriceListWithInfoFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsInfo($price_list);
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
                        'lowest_price' => number_format($info['lowest_price'] / 100, 2),
                        'discount' => $price['discount'],
                        'price_unit' => c('price_unit'),
                        'tag' => $price['tag'],
                    ),
                    'plus_user' => array(
                        'origin_price' => number_format($price['plus_origin_price'] / 100, 2),
                        'sale_price' => number_format($price['plus_sale_price'] / 100, 2),
                        'lowest_price' => number_format($info['plus_lowest_price'] / 100, 2),
                        'discount' => $price['plus_discount'],
                        'price_unit' => c('price_unit'),
                        'tag' => $price['plus_tag'],

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
                    'lowest_price' => number_format($info['lowest_price'] / 100, 2),
                    'discount' => $info['discount'],
                    'price_unit' => c('price_unit'),
                    'tag' => $info['tag'],

                ),
                'plus_user' => array(
                    'origin_price' => number_format($info['plus_origin_price'] / 100, 2),
                    'sale_price' => number_format($info['plus_sale_price'] / 100, 2),
                    'lowest_price' => number_format($info['plus_lowest_price'] / 100, 2),
                    'discount' => $info['plus_discount'],
                    'price_unit' => c('price_unit'),
                    'tag' => $info['plus_tag'],
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

        $db = db();
        $db->tableName = 'goods_price';
        $list = $db->findAll($where, $field, $sort, $limit_str);

        return $list;
    }

    public function getGoodsPriceListWithInfoFromDb($where = '', $field = array(), $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? implode(',', $field) : '*';
        $sort = $sort ? $sort : 'id desc';
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'goods_price';
        $sql = "(SELECT {$field} FROM `goods_price` as a LEFT JOIN `goods` as b ON a.goods_id = b.goods_id WHERE {$where} ORDER BY {$sort} LIMIT {$limit_str})";
        $list = $db->query($sql);

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
            $info = array(
                'goods_id' => $price['goods_id'],
                'name' => $goods_info[$price['goods_id']]['name'.$this->suffix],
                'cover_image' => $goods_info[$price['goods_id']]['cover_image'.$this->suffix],
//                'genres' => $goods_info[$price['goods_id']]['genres'.$this->suffix],
                'language_support' => $goods_info[$price['goods_id']]['language_support'.$this->suffix],
//                'file_size' => $goods_info[$price['goods_id']]['file_size'],
                'rating_score' => $goods_info[$price['goods_id']]['rating_score'],
                'rating_total' => $goods_info[$price['goods_id']]['rating_total'],
                'status' => $goods_info[$price['goods_id']]['status'],
                'price' => array(
                    'promo_date' => array(
                        'start_date' => $price['start_date'] ? : '',
                        'end_date' => $price['end_date'] ? : '',
                    ),
                    'non_plus_user' => array(
                        'origin_price' => number_format($price['origin_price'] / 100, 2),
                        'sale_price' => number_format($price['sale_price'] / 100, 2),
                        'lowest_price' => number_format($price['lowest_price'] / 100, 2),
                        'discount' => $price['discount'],
                        'price_unit' => c('price_unit'),
                        'tag' => $price['tag'],

                    ),
                    'plus_user' => array(
                        'origin_price' => number_format($price['plus_origin_price'] / 100, 2),
                        'sale_price' => number_format($price['plus_sale_price'] / 100, 2),
                        'lowest_price' => number_format($price['plus_lowest_price'] / 100, 2),
                        'discount' => $price['plus_discount'],
                        'price_unit' => c('price_unit'),
                        'tag' => $price['plus_tag'],
                    ),
                ),
            );

            if (!empty($info['cover_image']) && strpos($info['cover_image'], 'http') === false) {
//                $info['cover_image'] = c("playstation_image_domain") . $info['cover_image'] . '?imageView2/0/w/480/h/480';
                $info['cover_image'] = c("playstation_image_domain") . $info['cover_image'];
            }
            $list[] = $info;
        }

        return $list;
    }



}