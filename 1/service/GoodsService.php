<?php
class GoodsService extends BaseService
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

    public function detail($goods_id)
    {
        if (empty($goods_id)) {
            return $this->setError('param_goods_id_is_empty');
        }
        $goods_info = $this->getGoodsInfo($goods_id);
        $info = array(
            'goods_id'     => $goods_info['goods_id'],
            'name'         => $goods_info['name' . $this->suffix],
            'cover_image'  => $goods_info['cover_image' . $this->suffix],
            'description'  => $goods_info['description' . $this->suffix],
            'rating_score' => $goods_info['rating_score'],
            'rating_total' => $goods_info['rating_total'],
            'preview'      => $goods_info['preview'],
            'screenshots'  => $goods_info['screenshots'],
            'release_date' => $goods_info['release_date'],
            'publisher'    => $goods_info['publisher'],
            'developer'    => $goods_info['developer'],
        );

        $service = s('GoodsPrice');
        $price_info = $service->getGoodsPrice($goods_id);
        $info['price'] = $price_info;

        return $info;
    }

    public function getGoodsInfo($goods_id, $field = array())
    {
        if (empty($goods_id)) {
            return $this->setError('param_goods_id_is_empty');
        }
        $info = $this->getGoodsInfoFromDb($goods_id, $field);
        if (is_array($goods_id)) {
            foreach ($info as &$goods) {
                $goods['preview'] = $goods['preview'] ? json_decode($goods['preview'], true) : '';
                $goods['screenshots'] = $goods['screenshots'] ? json_decode($goods['screenshots'], true) : '';
            }
            unset($goods);
        } else {
            $info['preview'] = $info['preview'] ? json_decode($info['preview'], true) : '';
            $info['screenshots'] = $info['screenshots'] ? json_decode($info['screenshots'], true) : '';
        }

        return $info;
    }

    public function search($name, $page = 1)
    {
        if (empty($name)) {
            return $this->setError('param_name_is_empty', '请填写游戏名称');
        }
        $where = "name LIKE '%{$name}%' OR name_cn LIKE '%{$name}%'";
        $sort = "rating_total DESC";
        $goods_list = $this->getGoodsListFromDb($where, array(), $sort, $page);
        if (empty($goods_list)) {
            return array();
        }
        $goods_id_arr = array_column($goods_list, 'goods_id');
        $service = s('GoodsPrice');
        $goods_price = $service->getGoodsPrice($goods_id_arr);

        $list = array();
        foreach ($goods_list as $goods) {
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

    public function getGoodsListFromDb($where = '', $field = array(), $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? implode(',', $field) : '*';
        $sort = $sort ? $sort : 'id desc';
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'goods';
        $list = $db->findAll($where, $field, $sort, $limit_str);

        return $list;
    }

    protected function getGoodsInfoFromDb($goods_id, $field = array())
    {
        $db = pdo();
        $db->tableName = 'goods';
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

    public function getGoodsListWithPriceFromDb($where = '', $field = array(), $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? implode(',', $field) : '*';
        $sort = $sort ? $sort : 'id desc';
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'goods';
        $sql = "(SELECT {$field} FROM `goods` as a LEFT JOIN `goods_price` as b ON a.goods_id = b.goods_id WHERE {$where} ORDER BY {$sort} LIMIT {$limit_str})";
        $list = $db->query($sql);

        return $list;
    }
}