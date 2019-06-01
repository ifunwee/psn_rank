<?php
in(VERSION_PATH . "/service/GoodsService.php");
class GameService extends GoodsService
{
    public function getGameInfo($game_id, $field = array())
    {
        if (empty($game_id)) {
            return $this->setError('param_game_id_is_empty');
        }
        $info = $this->getGameInfoFromDb($game_id, $field);
        if (is_array($game_id)) {
            foreach ($info as &$item) {
                $goods['videos'] = $item['videos'] ? json_decode($item['videos'], true) : '';
                $goods['screenshots'] = $item['screenshots'] ? json_decode($item['screenshots'], true) : '';
                $genres_arr = $item['genres'] ? explode(',', $item['genres']) : '';
                if (!empty($genres_arr)) {
                    foreach ($genres_arr as $genre) {
                        $info['genres_cn'][] = $this->genres[$genre];
                    }
                }
            }
            unset($goods);
        } else {
            $info['videos'] = $info['videos'] ? json_decode($info['videos'], true) : '';
            $info['screenshots'] = $info['screenshots'] ? json_decode($info['screenshots'], true) : '';
            $genres_arr = $info['genres'] ? explode(',', $info['genres']) : '';
            if (!empty($genres_arr)) {
                foreach ($genres_arr as $genre) {
                    $info['genres_cn'][] = $this->genres[$genre];
                }
            }
        }

        return $info;
    }

    public function getGameInfoFromDb($game_id, $field = array())
    {
        $db = pdo();
        $db->tableName = 'game';
        $field = $field ? implode(',', $field) : '*';
        $result = array();
        if (is_array($game_id)) {
            $game_id = array_unique($game_id);
            $game_id_str = implode("','", $game_id);
            $where = "game_id in ('{$game_id_str}')";
            $list = $db->findAll($where, $field);
            foreach ($list as $game) {
                $result[$game['game_id']] = $game;
            }
        } else {
            $where = "game_id = '{$game_id}'";
            $result = $db->find($where, $field);
        }

        return $result;
    }

    public function getGameListFromDb($where = '', $field = array(), $sort = '', $page = 1, $limit = 20)
    {
        $where = $where ? $where : '1=1';
        $field = $field ? implode(',', $field) : '*';
        $sort = $sort ? $sort : 'id desc';
        $start = ($page - 1) * $limit;
        $limit_str = "{$start}, {$limit}";

        $db = pdo();
        $db->tableName = 'game';
        $list = $db->findAll($where, $field, $sort, $limit_str);

        return $list;
    }

    public function completeGoodsPrice($game_list)
    {
        $list = array();
        if (empty($game_list)) {
            return $list;
        }

        $service = s('goodsPrice');
        $goods_id_arr = array_column($game_list, 'main_goods_id');
        $goods_price = $service->getGoodsPrice($goods_id_arr);

        foreach ($game_list as $game) {
            $genres_arr = $game['genres'] ? explode(',', $game['genres']) : array();
            $genres = array();
            if (!empty($genres_arr)) {
                foreach ($genres_arr as $item) {
                    $genres[] = $this->genres[$item];
                }
            }

            $info = array(
                'goods_id' => $game['main_goods_id'],
                'name' => $game['display_name'],
                'cover_image' => $game['cover_image'],
                'genres' => $genres,
                'language_support' => $game['language_support'],
                'rating_score' => $game['rating_score'],
                'rating_total' => $game['rating_total'],
                'release_date' => $game['release_date'],
                'price' => $goods_price[$game['main_goods_id']],
                'game' => array(
                    'mc_score' => $game['mc_score'] ? : '0',
                    'is_only' => $game['is_only'] ? : '',
                    'franchises' => $game['franchises'] ? : '',
                    'post_num' => $game['post_num'] ? : '0',
                    'status' => $game['status']
                ),
            );

            $info['cover_image'] && $info['cover_image'] = s('Common')->handlePsnImage($info['cover_image']);

            $list[] = $info;
        }

        return $list;
    }

    public function getTab()
    {
        $data = array(
            array(
                'title' => '最高评分',
                'type' => 'best',
            ),
            array(
                'title' => '最新游戏',
                'type' => 'latest',
            ),
            array(
                'title' => '热门游戏',
                'type' => 'hot',
            ),
            array(
                'title' => '即将发售',
                'type' => 'coming',
            ),
            array(
                'title' => '新游推荐',
                'type' => 'fresh',
            ),
        );

        return $data;
    }

    public function getTabList($type, $page = 1, $limit = 20)
    {
        if (empty($type)) {
            return $this->setError('param_type_is_empty');
        }

        if (!in_array($type, $this->discovery_type)) {
            return $this->setError('invalid_discovery_type');
        }

        switch ($type) {
            case 'fresh':
                $where = "release_date <= UNIX_TIMESTAMP() and (mc_score <> NULL or rating_total >= 10)";
                $sort = 'release_date desc, id desc';
                $game_list = $this->getGameListFromDb($where, array(), $sort, $page, $limit);
                $list = $this->completeGoodsPrice($game_list);
                break;
            case 'latest':
                $where = "release_date <= UNIX_TIMESTAMP()";
                $sort = 'release_date desc, id desc';
                $game_list = $this->getGameListFromDb($where, array(), $sort, $page, $limit);
                $list = $this->completeGoodsPrice($game_list);
                break;
            case 'coming':
                $where = "release_date > UNIX_TIMESTAMP()";
                $sort = 'release_date asc, id desc';
                $game_list = $this->getGameListFromDb($where, array(), $sort, $page, $limit);
                $list = $this->completeGoodsPrice($game_list);
                break;
            case 'best':
                $where = "1=1";
                $sort = 'mc_score desc, rating_score desc';
                $game_list = $this->getGameListFromDb($where, array(), $sort, $page, $limit);
                $list = $this->completeGoodsPrice($game_list);
                break;
            case 'hot':
                $where = "rating_total > 100 and release_date <= UNIX_TIMESTAMP()";
                $sort = 'rating_total desc, id desc';
                $game_list = $this->getGameListFromDb($where, array(), $sort, $page, $limit);
                $list = $this->completeGoodsPrice($game_list);
                break;
            default :
                return $this->setError('invalid_type');
        }
        return $list;
    }

    public function search($name, $page = 1)
    {
        if (empty($name)) {
            return $this->setError('param_name_is_empty', '请填写游戏名称');
        }

        $where = "(origin_name LIKE '%{$name}%' OR display_name LIKE '%{$name}%')";
        $sort = "rating_total DESC";
        $game_list = $this->getGameListFromDb($where, array(), $sort, $page);
        $list = $this->completeGoodsPrice($game_list);

        return $list;
    }
}
