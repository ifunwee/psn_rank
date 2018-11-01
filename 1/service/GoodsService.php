<?php
class GoodsService extends BaseService
{
    protected $suffix = '_cn';
    protected $genres = array(
        'Action'                  => '动作',
        'Adventure'               => '冒险',
        'Arcade'                  => '街机',
        'Board Games'             => '桌上游戏',
        'Casual'                  => '休闲',
        'Education'               => '教育',
        'Family'                  => '家庭',
        'Fighting'                => '格斗',
        'Fitness'                 => '健身',
        'Horror'                  => '恐怖',
        'Music/Rhythm'            => '音乐&节奏',
        'Party'                   => '派对',
        'Platformer'              => '横板过关',
        'Puzzle'                  => '益智',
        'Quiz'                    => '问答游戏',
        'Racing'                  => '赛车',
        'Role-Playing Games (RPG)'=> '角色扮演',
        'Shooter'                 => '射击',
        'Simulation'              => '模拟',
        'Simulator'               => '模拟器',
        'Sports'                  => '运动',
        'Strategy'                => '战略',
        'Unique'                  => '独特游戏',
    );

    protected $discovery_type = array(
        'latest',     //最新游戏
        'coming',     //即将到来
        'hot',       //热门游戏
        'best',      //评分最高
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

    public function detail($goods_id, $open_id)
    {
        if (empty($goods_id)) {
            return $this->setError('param_goods_id_is_empty');
        }
        $goods_info = $this->getGoodsInfo($goods_id);
        $info = array(
            'goods_id'         => $goods_info['goods_id'],
            'name'             => $goods_info['name' . $this->suffix] ? : '',
            'cover_image'      => $goods_info['cover_image' . $this->suffix] ? : '',
            'description'      => $goods_info['description' . $this->suffix] ? : '',
            'rating_score'     => $goods_info['rating_score'],
            'rating_total'     => $goods_info['rating_total'],
            'preview'          => $goods_info['preview'],
            'screenshots'      => $goods_info['screenshots'],
            'release_date'     => $goods_info['release_date'],
            'publisher'        => $goods_info['publisher'],
            'developer'        => $goods_info['developer'],
            'genres'           => $goods_info['genres' . $this->suffix] ? : '',
            'file_size'        => $goods_info['file_size'],
            'language_support' => $goods_info['language_support' . $this->suffix] ? : '',
            'status'           => $goods_info['status'],
            'is_follow'        => '0',
        );

        $service = s('GoodsPrice');
        $price_info = $service->getGoodsPrice($goods_id);
        $info['price'] = $price_info;
        $info['price_history'] = $this->getPriceHistory($goods_id);
        $current = array(
            'date' => strtotime(date('Y-m-d'))*1000,
            'price' => floatval($price_info['non_plus_user']['sale_price']),
            'plus_price' => floatval($price_info['plus_user']['sale_price']),
        );
        $info['price_history'][] = $current;

        if (!empty($open_id)) {
            $service = s('Follow');
            $is_follow = $service->isFollow($open_id, $goods_id);
            $info['is_follow'] = $is_follow;
        }

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
                $goods['file_size'] = $goods['file_size'] ? $goods['file_size'].$goods['file_size_unit'] : '';
                $genres_arr = $goods['genres'] ? explode(',', $goods['genres']) : '';
                foreach ($genres_arr as $genre) {
                    $goods['genres_cn'][] = $this->genres[$genre];
                }
            }
            unset($goods);
        } else {
            $info['preview'] = $info['preview'] ? json_decode($info['preview'], true) : '';
            $info['screenshots'] = $info['screenshots'] ? json_decode($info['screenshots'], true) : '';
            $info['file_size'] = $info['file_size'] ? $info['file_size'].$info['file_size_unit'] : '';
            $genres_arr = $info['genres'] ? explode(',', $info['genres']) : '';
            if (!empty($genres_arr)) {
                foreach ($genres_arr as $genre) {
                    $info['genres_cn'][] = $this->genres[$genre];
                }
            }
        }

        return $info;
    }

    public function search($name, $page = 1)
    {
        if (empty($name)) {
            return $this->setError('param_name_is_empty', '请填写游戏名称');
        }
//        $where = "(name LIKE '%{$name}%' OR name_cn LIKE '%{$name}%') and status > 0";
        $where = "(name LIKE '%{$name}%' OR name_cn LIKE '%{$name}%')";
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
                'goods_id' => $goods['goods_id'] ? : '',
                'name' => $goods['name'.$this->suffix] ? : '',
                'cover_image' => $goods['cover_image'.$this->suffix] ? : '',
                'rating_score' => $goods['rating_score'] ? : '',
                'rating_total' => $goods['rating_total'] ? : '',
                'language_support' => $goods['language_support'.$this->suffix] ? : '',
                'status' => $goods['status'],
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

    public function getPriceHistory($goods_id)
    {
        $db = pdo();
        $today = strtotime(date('Y-m-d', time()));
        $db->tableName = 'goods_price_history';
        $where = "goods_id = '{$goods_id}' and date < {$today}";
        $list = $db->findAll($where, 'date,price,plus_price', 'date asc');

        foreach ($list as &$value) {
            $value['date'] = $value['date'] * 1000;
            $value['price'] = $value['price'] / 100;
            $value['plus_price'] = $value['plus_price'] / 100;
        }
        unset($value);
        return $list;
    }

    private function completeGoodsPrice($goods_list)
    {
        $list = array();
        if (empty($goods_list)) {
            return $list;
        }

        $service = s('goodsPrice');
        $goods_id_arr = array_column($goods_list, 'goods_id');
        $goods_price = $service->getGoodsPrice($goods_id_arr);

        foreach ($goods_list as $goods) {
            $info = array(
                'goods_id' => $goods['goods_id'],
                'name' => $goods['name'.$this->suffix],
                'cover_image' => $goods['cover_image'.$this->suffix],
                'language_support' => $goods['language_support'.$this->suffix],
                'rating_score' => $goods['rating_score'],
                'rating_total' => $goods['rating_total'],
                'status' => $goods['status'],
                'price' => $goods_price[$goods['goods_id']],
            );
            $list[] = $info;
        }

        return $list;
    }

    public function getDiscoveryTab()
    {
        $data = array(
            array(
                'title' => '最新游戏',
                'type' => 'latest',
            ),
            array(
                'title' => '即将发售',
                'type' => 'coming',
            ),
            array(
                'title' => '热门游戏',
                'type' => 'hot',
            ),
            array(
                'title' => '最高评分',
                'type' => 'best',
            ),
        );

        return $data;
    }

    public function getDiscoveryList($type, $page = 1, $limit = 20)
    {
        if (empty($type)) {
            return $this->setError('param_type_is_empty');
        }

        if (!in_array($type, $this->discovery_type)) {
            return $this->setError('invalid_discovery_type');
        }

        switch ($type) {
            case 'latest':
                $where = "release_date <= UNIX_TIMESTAMP() and status <> 0";
                $sort = 'release_date desc, id desc';
                $goods_list = $this->getGoodsListFromDb($where, '', $sort, $page, $limit);

                $list = $this->completeGoodsPrice($goods_list);
                break;
            case 'coming':
                $where = "release_date > UNIX_TIMESTAMP() and status <> 0";
                $sort = 'release_date asc, id desc';
                $goods_list = $this->getGoodsListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsPrice($goods_list);
                break;
            case 'best':
                $where = "rating_total > 100 and release_date <= UNIX_TIMESTAMP() and status <> 0";
                $sort = 'rating_score desc, id desc';
                $goods_list = $this->getGoodsListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsPrice($goods_list);
                break;
            case 'hot':
                $where = "rating_total > 100 and release_date <= UNIX_TIMESTAMP() and status <> 0";
                $sort = 'rating_total desc, id desc';
                $goods_list = $this->getGoodsListFromDb($where, '', $sort, $page, $limit);
                $list = $this->completeGoodsPrice($goods_list);
                break;
            default :
                return $this->setError('invalid_type');
        }
        return $list;
    }
}