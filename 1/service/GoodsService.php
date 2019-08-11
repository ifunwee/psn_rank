<?php
class GoodsService extends BaseService
{
    protected $suffix = '_cn';
    protected $genres = array(
        'action'                  => '动作',
        'adventure'               => '冒险',
        'arcade'                  => '街机',
        'board_games'             => '桌上游戏',
        'casual'                  => '休闲',
        'education'               => '教育',
        'family'                  => '家庭',
        'fighting'                => '格斗',
        'fitness'                 => '健身',
        'horror'                  => '恐怖',
        'music_rhythm'            => '音乐&节奏',
        'party'                   => '派对',
        'platformer'              => '横板过关',
        'puzzle'                  => '益智',
        'quiz'                    => '问答游戏',
        'racing'                  => '赛车',
        'role_playing_games'      => '角色扮演',
        'shooter'                 => '射击',
        'simulation'              => '模拟',
        'simulator'               => '模拟器',
        'sports'                  => '运动',
        'strategy'                => '战略',
        'unique'                  => '独特游戏',
    );

    protected $discovery_type = array(
        'latest',     //最新游戏
        'coming',     //即将到来
        'hot',       //热门游戏
        'best',      //评分最高
        'fresh',      //新游推荐
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
        $origin_domain = c('playstation_media_origin_domain');
        $goods_info = $this->getGoodsInfo($goods_id);
        if ($goods_info['screenshots']) {
            foreach ($goods_info['screenshots'] as $key => &$value) {
                $value['url'] = s('Common')->handlePsnImage($value['url'], 720, 480, 'media');
            }
        }
        unset($value);

        //视频暂时不走cdn 拼上索尼原始域名
        if ($goods_info['preview']) {
            foreach ($goods_info['preview'] as $key => &$value) {
                $value['url'] = $origin_domain.$value['url'];
            }
        }
        unset($value);


        $info = array(
            'goods_id'         => $goods_info['goods_id'],
            'is_main'          => $goods_info['is_main'],
            'name'             => $goods_info['name' . $this->suffix] ? : '',
            'display_name'     => $goods_info['name' . $this->suffix] ? : '',
            'oringin_name'     => $goods_info['name'] ? : '',
            'cover_image'      => $goods_info['cover_image'] ? : '',
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

        $info['cover_image'] = s('Common')->handlePsnImage($info['cover_image'], 480, 480);

        //获取游戏资料
        $service = s('Game');
        $game_info = $service->getGameInfo($goods_info['game_id']);
        $game = array(
            'game_id' => $goods_info['game_id'],
            'main_goods_id' => $game_info['main_goods_id'] ? : '',
            'display_name' => $game_info['display_name'] ? : '',
            'introduction' => $game_info['introduction'] ? : '',
            'cover_image' => s('Common')->handlePsnImage($game_info['cover_image'], 480, 480),
            'developer' => $game_info['developer'] ? : '',
            'publisher' => $game_info['publisher'] ? : '',
            'franchises' => $game_info['franchises'] ? : '',
            'is_only' => $game_info['is_only'] ? : '',
            'release_date' => $game_info['release_date'] ? : '',
            'play_time' => $game_info['play_time'] ? : '',
            'difficulty' => $game_info['difficulty'] ? : '',
            'local_players' => $game_info['local_players'] ? : '',
            'online_players' => $game_info['online_players'] ? : '',
            'is_chinese_support' => $game_info['is_chinese_support'] ? : '',
            'mc_score' => $game_info['mc_score'] ? : '',
            'post_num' => $game_info['post_num'] ? : '0',
        );

        $info['game'] = $game;

        //获取相关游戏商品
        $relation_goods = $this->getRelationGoods($goods_id, $goods_info['game_id']);
        if ($this->hasError()) {
            $this->flushError();
            $relation_goods = array();
        }
        $info['relation_goods'] = $relation_goods;

        //获取相同开发商游戏
        $same_developer_goods = $this->getSameDeveloperGoods($game['game_id'], $game['developer']);
        if ($this->hasError()) {
            $this->flushError();
            $same_developer_goods = array();
        }
        $info['same_developer_goods'] = $same_developer_goods;


        //获取奖杯数据
        if (!empty($game_info['np_communication_id'])) {
            $service = s('Profile');
            $game_trophy = $service->getTrophyInfo($game_info['np_communication_id'], 0);
            $bronze = $game_trophy['game_info']['defined_trophies']['bronze'];
            $silver = $game_trophy['game_info']['defined_trophies']['silver'];
            $gold = $game_trophy['game_info']['defined_trophies']['gold'];
            $platinum = $game_trophy['game_info']['defined_trophies']['platinum'];
            $trophy_icon = $game_trophy['trophy_info']['trophy_icon_url'] ? : $game_trophy['game_info']['trophy_title_icon_url'];
            $trophy_difficulty = $game_trophy['trophy_info']['trophy_earned_rate'] ? : '';
            $trophy = array(
                'total' => strval($bronze + $silver + $gold + $platinum),
                'bronze' => strval($bronze),
                'silver' => strval($silver),
                'gold' => strval($gold),
                'platinum' => strval($platinum),
                'trophy_icon' => strval($trophy_icon),
                'trophy_difficulty' => strval($trophy_difficulty),
            );
            $info['trophy'] = $trophy;
        }

        return $info;
    }

    public function getGoodsInfo($goods_id, $field = '')
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
                $genres_arr = $goods['genres'] ? explode(',', $goods['genres']) : $goods['genres'] = array();
                if (!empty($genres_arr)) {
                    foreach ($genres_arr as $item) {
                        $goods['genres_cn'][] = $this->genres[$item];
                    }
                } else {
                    $goods['genres_cn'] = array();
                }
            }
            unset($goods);
        } else {
            $info['preview'] = $info['preview'] ? json_decode($info['preview'], true) : '';
            $info['screenshots'] = $info['screenshots'] ? json_decode($info['screenshots'], true) : '';
            $info['file_size'] = $info['file_size'] ? $info['file_size'].$info['file_size_unit'] : '';
            $genres_arr = $info['genres'] ? explode(',', $info['genres']) : $info['genres'] = array();
            if (!empty($genres_arr)) {
                foreach ($genres_arr as $item) {
                    $info['genres_cn'][] = $this->genres[$item];
                }
            } else {
                $info['genres_cn'] = array();
            }
        }

        return $info;
    }

    public function search($name, $page = 1)
    {
        if (empty($name)) {
            return $this->setError('param_name_is_empty', '请填写游戏名称');
        }
        $where = "(name LIKE '%{$name}%' OR name_cn LIKE '%{$name}%') and status > 0";
//        $where = "(name LIKE '%{$name}%' OR name_cn LIKE '%{$name}%')";
        $sort = "status DESC, rating_total DESC";
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

            $info['cover_image'] = s('Common')->handlePsnImage($info['cover_image']);
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

    public function completeGoodsPrice($goods_list)
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

            $info['cover_image'] && $info['cover_image'] = s('Common')->handlePsnImage($info['cover_image']);
            $list[] = $info;
        }

        return $list;
    }

    public function getRelationGoods($goods_id, $game_id)
    {
        if (empty($goods_id)) {
            return $this->setError('param_goods_id_is_empty');
        }

        if (empty($game_id)) {
            return $this->setError('param_game_id_is_empty');
        }

        $db = pdo();
        $sql = "select * from goods where game_id = {$game_id} and goods_id <> '{$goods_id}' and status > 0";
        $goods_list = $db->query($sql);

        if (empty($goods_list)) {
            return array();
        }

        $list = $this->completeGoodsPrice($goods_list);
        return $list;
    }

    public function getSameDeveloperGoods($game_id, $developer)
    {
        if (empty($game_id)) {
            return $this->setError('param_game_id_is_empty');
        }

        if (empty($developer)) {
            return $this->setError('param_developer_is_empty');
        }

        $developer = addslashes($developer);
        $db = pdo();
        $sql = "select * from game where developer = '{$developer}' and game_id <> {$game_id} order by rating_total desc limit 6 ";
        $game_list = $db->query($sql);

        if (empty($game_list)) {
            return array();
        }

        $list = array();
        foreach ($game_list as $game) {
            $item['goods_id'] = $game['main_goods_id'];
            $item['cover_image'] = s('Common')->handlePsnImage($game['cover_image']);
            $item['name'] = $game['display_name'];

            $list[] = $item;
        }

        return $list;
    }


}