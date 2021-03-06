<?php
class HandlePs4Game extends BaseService
{
    protected $genres = array(
        'Action'                  => 'action',
        'Adventure'               => 'adventure',
        'Arcade'                  => 'arcade',
        'Board Games'             => 'board_games',
        'Casual'                  => 'casual',
        'Education'               => 'education',
        'Family'                  => 'family',
        'Fighting'                => 'fighting',
        'Fitness'                 => 'fitness',
        'Horror'                  => 'horror',
        'MUSIC/RHYTHM'            => 'music_rhythm',
        'Party'                   => 'party',
        'Platformer'              => 'platformer',
        'Puzzle'                  => 'puzzle',
        'Quiz'                    => 'quiz',
        'Racing'                  => 'racing',
        'Role-Playing Games (RPG)'=> 'role_playing_games',
        'Shooter'                 => 'shooter',
        'Simulation'              => 'simulation',
        'Simulator'               => 'simulator',
        'Sports'                  => 'sports',
        'Strategy'                => 'strategy',
        'Unique'                  => 'unique',
    );

    /**
     * np_title_id
     */
    public function hk()
    {
        $db               = pdo();
        $redis            = r('psn_redis');
        $hk_code_list_key = redis_key('ps4_hk_game_code_list');
        $db->tableName    = 'game_code';
        $list             = $db->findAll('1=1', '*', 'id asc');
        if (empty($list)) {
            return false;
        }

        foreach ($list as $info) {
            if (empty($info['store_game_code'])) {
                continue;
            }

            $product_id_arr = explode('-', $info['store_game_code']);
            $np_title_id    = $product_id_arr[1];
            $redis->zAdd($hk_code_list_key, time(), $np_title_id);
        }

        echo "脚本处理完毕:" . $redis->zCard($hk_code_list_key);
    }

    /**
     * 游戏商品入库
     */
    public function goods()
    {
        $db            = pdo();
        $db->tableName = 'game_code';
        $last_id       = 99999;
        $list          = $db->findAll("id < {$last_id}", '*', 'id desc');
        if (empty($list)) {
            return false;
        }

        $service = s('Common');
        $i       = 1;
        foreach ($list as $info) {
            if (empty($info['store_game_code'])) {
                continue;
            }

            $url      = 'https://store.playstation.com/valkyrie-api/en/hk/19/resolve/' . $info['store_game_code'];
            $response = $service->curl($url);
            if ($service->hasError()) {
                echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();

                $url = 'https://store.playstation.com/valkyrie-api/en/HK/19/resolve/' . $info['store_game_code'];
                $response = $service->curl($url);
                if ($service->hasError()) {
                    echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                    $service->flushError();
                    continue;
                }
            }
            $data = json_decode($response, true);
            if (empty($data['included'])) {
                echo "商品 {$info['store_game_code']} 获取数据失败 \r\n";
                var_dump($data);
                continue;
            }

            $item = $data['included'][0];
            $attr = $item['attributes'];

            $default_sku_id = $attr['default-sku-id'];
            $index = null;

            if (is_array($attr['skus'])) {
                foreach ($attr['skus'] as $key => $sku_info) {
                    if ($sku_info['id'] == $default_sku_id) {
                        $index = $key;
                    }
                }
            }

            $product_id_arr = explode('-', $info['store_game_code']);
            $np_title_id    = $product_id_arr[1];

            $parent_product_id_arr = array();
            $parent_goods_id    = $attr['parent']['id'];
            $parent_goods_id && $parent_product_id_arr = explode('-', $parent_goods_id);

            $parent_np_title_id = $parent_product_id_arr[1];

            $db->tableName     = 'goods';
            $where['goods_id'] = $item['id'];
            $result            = $db->find($where);

            if (is_array($attr['genres'])) {
                foreach ($attr['genres'] as &$member) {
                    if (!empty($this->genres[$member])) {
                        $member = $this->genres[$member];
                    } else {
                        echo "无法识别的genres: {$member} \r\n";
                        continue;
                    }
                }
                unset($member);
            }
            switch ($attr['ps-camera-compatibility']) {
                case 'incompatible': $ps_camera = 0; break;
                case 'compatible': $ps_camera = 1; break;
                case 'required': $ps_camera = 2; break;
                default: $ps_camera = null;
            }

            switch ($attr['ps-move-compatibility']) {
                case 'incompatible': $ps_move = 0; break;
                case 'compatible': $ps_move = 1; break;
                case 'required': $ps_move = 2; break;
                default: $ps_move = null;
            }

            switch ($attr['ps-vr-compatibility']) {
                case 'incompatible': $ps_vr = 0; break;
                case 'compatible': $ps_vr = 1; break;
                case 'required': $ps_vr = 2; break;
                default: $ps_vr = null;
            }

            $info = array(
                'goods_id'           => $item['id'] ?: '',
                'np_title_id'        => $np_title_id ?: '',
                'parent_np_title_id' => $parent_np_title_id ?: '',
                'name'               => $attr['name'] ?: '',
                'cover_image'        => $attr['thumbnail-url-base'] ?: '',
                'description'        => $attr['long-description'] ?: '',
                'rating_score'       => $attr['star-rating']['score'] ?: 0,
                'rating_total'       => $attr['star-rating']['total'] ?: 0,
                'release_date'       => $attr['release-date'] ? strtotime($attr['release-date']) : 0,
                'publisher'          => $attr['provider-name'] ?: '',
                'developer'          => '',
                'file_size'          => $attr['file-size']['value'] ?: 0,
                'file_size_unit'     => $attr['file-size']['unit'] ?: '',
                'genres'             => $attr['genres'] ? implode(',', $attr['genres']) : '',
                'language_support'   => is_numeric($index) ? $attr['skus'][$index]['name'] : '',
                'ps_camera'          => $ps_camera,
                'ps_move'            => $ps_move,
                'ps_vr'              => $ps_vr,
                'content_type'       => strtolower($attr['game-content-type']),
                'primary'            => strtolower($attr['primary-classification']),
                'secondary'          => strtolower($attr['secondary-classification']),
                'tertiary'           => strtolower($attr['tertiary-classification']),
            );

            $media = array(
                'preview'            => !empty($attr['media-list']['preview']) ? str_replace('https:\/\/apollo2.dl.playstation.net','',json_encode($attr['media-list']['preview'])) : '',
                'screenshots'        => !empty($attr['media-list']['screenshots']) ? str_replace('https:\/\/apollo2.dl.playstation.net','',json_encode($attr['media-list']['screenshots'])) : '',
            );
            if (!empty($info['cover_image'])) {
                $info['cover_image'] = str_replace('https://store.playstation.com', '', $info['cover_image']);
            }
            $info['description'] = strip_tags($info['description'], '<br>');
            $info['description'] = preg_replace('/<br\\s*?\/??>/i', chr(13) . chr(10), $info['description']);

            if (empty($result)) {
                $info['preview'] = $media['preview'];
                $info['screenshots'] = $media['screenshots'];
                $info['create_time'] = time();
                $db->insert($info);
            } else {
                $info['update_time'] = time();
                empty($result['preview']) && $info['preview'] = $media['preview'];
                empty($result['screenshots']) && $info['screenshots'] = $media['screenshots'];
                $condition['goods_id']     = $result['goods_id'];
                $db->update($info, $condition);
            }

            echo "商品 {$item['id']} 入库完成 $i";
            echo "\r\n";
            $i++;
        }
        $date = date('Y-m-d H:i:s', time());
        echo "{$date} 脚本处理完毕";
    }

    /**
     * 游戏价格更新
     */
    public function price()
    {
        $start = date('Y-m-d H:i:s', time());
        echo "{$start} 脚本开始运行 \r\n";

        $db            = pdo();
        $redis         = r('psn_redis');
        $db->tableName = 'game_code';
        $last_id       = 0;
        $list          = $db->findAll("id > {$last_id}", '*', 'id desc');
        if (empty($list)) {
            return false;
        }

        $service = s('Common');
        $id = 0;
        $i = 1;
        foreach ($list as $info) {
            if (empty($info['store_game_code'])) {
                continue;
            }

            $url      = 'https://store.playstation.com/valkyrie-api/en/hk/19/resolve/' . $info['store_game_code'];
            $response = $service->curl($url);

            if ($service->hasError()) {
                echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();

                $url = 'https://store.playstation.com/valkyrie-api/en/HK/19/resolve/' . $info['store_game_code'];
                $response = $service->curl($url);
                if ($service->hasError()) {
                    echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                    $service->flushError();
                    $fail_list_key = redis_key('price_update_fail_list');
                    $redis->lpush($fail_list_key, $info['store_game_code']);
                    continue;
                }
            }
            $data = json_decode($response, true);

            $result = $this->handleData($data, $info['store_game_code']);
            if ($this->hasError()) {
                echo "商品价格 {$info['store_game_code']} 更新失败： {$this->getErrorCode()} \r\n";
                $this->flushError();
                continue;
            }
            $result && $id = $result;
            echo "商品价格 {$info['store_game_code']} 更新完成 $i \r\n";
            $i++;
        }

        $end = date('Y-m-d H:i:s', time());
        $history_tips = $id ? "历史价格id新增至 {$id}" : '';
        $cost = ceil((strtotime($end) - strtotime($start))/60);
        echo "{$end} 脚本处理完毕 处理数据{$i}条 {$history_tips} 耗时：{$cost}分 \r\n";
    }

    /**
     * 价格数据处理
     * @param $data
     * @param $handle_goods_id
     *
     * @return mixed
     */
    public function handleData($data, $handle_goods_id)
    {
        $db = pdo();
        $goods_id = $data['data']['relationships']['children']['data'][0]['id'];
        if (empty($goods_id) || $handle_goods_id != $goods_id) {
            $goods['status'] = -1;
            $goods['update_time'] = time();
            $db->tableName = 'goods';
            $db->update($goods, array('goods_id' => $handle_goods_id));
            $db->tableName = 'goods_price';
            $db->update($goods, array('goods_id' => $handle_goods_id));
            return $this->setError('goods_id_is_not_match');
        }

        $item = $data['included'][0];
        $attr = $item['attributes'];
        $default_sku_id = $attr['default-sku-id'];
        $index = null;

        if (empty($default_sku_id)) {
            $db->tableName = 'goods';
            $goods['status'] = 0;
            $goods['update_time'] = time();
            $db->update($goods, array('goods_id' => $handle_goods_id));
            $db->tableName = 'goods_price';
            $db->update($goods, array('goods_id' => $handle_goods_id));
            return $this->setError('default_sku_id_is_empty');
        }

        if (!is_array($attr['skus'])) {
            return $this->setError('skus_info_is_invalid');
        }
        foreach ($attr['skus'] as $key => $sku_info) {
            if ($sku_info['id'] == $default_sku_id) {
                $index = $key;
            }
        }

        if (!is_numeric($index)) {
            return $this->setError('find_default_sku_id_fail');
        }

        $status = empty($attr['skus'][$index]['prices']) ? 0 : 1;
        $status = empty($attr['skus'][$index]['is-preorder']) ? $status : 2;

        $sku_price = null;
        $sku_reward = array();
        $is_ea_discount = 0;
        foreach ($data['included'] as $info) {
            if ($info['id'] == $default_sku_id) {
                $sku_price = $info['attributes']['price'];
                if (empty($info['attributes']['rewards'])) {
                    continue;
                }
                foreach ($info['attributes']['rewards'] as $reward) {
                    $sku_reward[$reward['discount']] = $reward;
                    if ($reward['isEAAccess'] === true) {
                        $is_ea_discount = 1;
                    }
                }
            }
        }

        $db->tableName = 'goods';
        $goods['status'] = $status;
        $goods['update_time'] = time();
        $db->update($goods, array('goods_id' => $handle_goods_id));

        $db->tableName     = 'goods_price';
        $where['goods_id'] = $item['id'];
        $result            = $db->find($where);

        $origin_price       = $attr['skus'][$index]['prices']['non-plus-user']['strikethrough-price']['value'];
        $sale_price         = $attr['skus'][$index]['prices']['non-plus-user']['actual-price']['value'];
        $discount           = $attr['skus'][$index]['prices']['non-plus-user']['discount-percentage'];
        $plus_origin_price  = $attr['skus'][$index]['prices']['plus-user']['strikethrough-price']['value'];
        $plus_sale_price    = $attr['skus'][$index]['prices']['plus-user']['actual-price']['value'];
        $plus_discount      = $attr['skus'][$index]['prices']['plus-user']['discount-percentage'];
        $start_date         = $attr['skus'][$index]['prices']['non-plus-user']['availability']['start-date'];
        $end_date           = $attr['skus'][$index]['prices']['non-plus-user']['availability']['end-date'];

        //EAAccess 特殊折扣处理
        if ((int)$discount > 0 && $sku_reward[$discount]['isEAAccess'] === true) {
            $sale_price = $sku_price;
            $origin_price = $sku_price;
            $discount = 0;
        }

        if ((int)$plus_discount > 0 && $sku_reward[$plus_discount]['isEAAccess'] === true) {
            $plus_sale_price = $sku_price;
            $plus_origin_price = $sku_price;
            $plus_discount = 0;
        }

        $info = array(
            'goods_id'           => $item['id'] ?: '',
            'origin_price'       => is_numeric($origin_price) ? $origin_price : null,
            'sale_price'         => is_numeric($sale_price) ? $sale_price : null,
            'discount'           => is_numeric($discount) ? $discount : null,
            'plus_origin_price'  => is_numeric($plus_origin_price) ? $plus_origin_price : null,
            'plus_sale_price'    => is_numeric($plus_sale_price) ? $plus_sale_price : null,
            'plus_discount'      => is_numeric($plus_discount) ? $plus_discount : null,
            'start_date'         => $start_date ? strtotime($start_date) : 0,
            'end_date'           => $end_date ? strtotime($end_date) : 0,
            'status'             => $status,
            'is_ea_discount'     => $is_ea_discount,
        );

        if (empty($result)) {
            $db->tableName = 'goods_price';
            $info['lowest_price'] = $info['sale_price'];
            $info['plus_lowest_price'] = $info['plus_sale_price'];
            $info['create_time'] = time();
            $db->insert($info);

            $db->tableName = 'goods_price_history';
            $data = array(
                'goods_id' => $item['id'],
                'price' => $info['sale_price'],
                'origin_price' => $info['origin_price'],
                'discount' => $info['discount'],
                'plus_price' => $info['plus_sale_price'],
                'plus_origin_price' => $info['plus_origin_price'],
                'plus_discount' => $info['plus_discount'],
                'start_date' => $info['start_date'],
                'end_date' => $info['end_date'],
                'date' => strtotime(date('Y-m-d', time())),
                'create_time' => time(),
            );
            $id = $db->insert($data);
        } else {
            if ((is_numeric($sale_price) && $sale_price != $result['sale_price']) || (is_numeric($plus_sale_price) && $plus_sale_price != $result['plus_sale_price'])) {
                //判断价格是否新低
                if ($sale_price == $result['lowest_price']) {
                    //持平
                    $info['tag'] = 2;
                } else if ($sale_price < $result['lowest_price']) {
                    $info['lowest_price'] = $sale_price;
                    //新低
                    $info['tag'] = 1;
                } else {
                    //普通
                    $info['tag'] = 0;
                }

                //判断会员价格是否新低
                if ($plus_sale_price == $result['plus_lowest_price']) {
                    //持平
                    $info['plus_tag'] = 2;
                } else if ($plus_sale_price < $result['plus_lowest_price']) {
                    $info['plus_lowest_price'] = $plus_sale_price;
                    //新低
                    $info['plus_tag'] = 1;
                } else {
                    //普通
                    $info['plus_tag'] = 0;
                }

                $db->tableName     = 'goods_price';
                $info['update_time'] = time();
                $db->update($info, $where);

                $db->tableName = 'goods_price_history';
                $data = array(
                    'goods_id' => $item['id'],
                    'price' => $info['sale_price'],
                    'origin_price' => $info['origin_price'],
                    'discount' => $info['discount'],
                    'tag' => $info['tag'],
                    'plus_price' => $info['plus_sale_price'],
                    'plus_origin_price' => $info['plus_origin_price'],
                    'plus_discount' => $info['plus_discount'],
                    'plus_tag' => $info['plus_tag'],
                    'start_date' => $info['start_date'],
                    'end_date' => $info['end_date'],
                    'date' => strtotime(date('Y-m-d', time())),
                    'create_time' => time(),
                );
                $id = $db->insert($data);
            } else {
                $db->tableName     = 'goods_price';
                $info['update_time'] = time();
                $db->update($info, $where);
            }
        }

        unset($data);
        unset($info);
        unset($result);

        return $id;
    }

    /**
     * 游戏中文更新
     */
    public function language()
    {
        $db            = pdo();
        $db->tableName = 'game_code';
        $last_id       = 0;
        $list          = $db->findAll("id > {$last_id}", '*', 'id desc');
        if (empty($list)) {
            return false;
        }

        $service = s('Common');
        $i       = 1;
        foreach ($list as $info) {
            if (empty($info['store_game_code'])) {
                continue;
            }

            $url      = 'https://store.playstation.com/valkyrie-api/zh/hk/19/resolve/' . $info['store_game_code'];
            $response = $service->curl($url);
            if ($service->hasError()) {
                echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();

                $url = 'https://store.playstation.com/valkyrie-api/zh/HK/19/resolve/' . $info['store_game_code'];
                $response = $service->curl($url);
                if ($service->hasError()) {
                    echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                    $service->flushError();
                    continue;
                }
            }
            $data = json_decode($response, true);
            if (empty($data['included'])) {
                echo "商品 {$info['store_game_code']} 获取数据失败 \r\n";
                var_dump($data);
                continue;
            }

            $item = $data['included'][0];
            $attr = $item['attributes'];

            $default_sku_id = $attr['default-sku-id'];
            $index = null;

            if (is_array($attr['skus'])) {
                foreach ($attr['skus'] as $key => $sku_info) {
                    if ($sku_info['id'] == $default_sku_id) {
                        $index = $key;
                    }
                }
            }

            $db->tableName     = 'goods';
            $where['goods_id'] = $item['id'];
            $result = $db->find($where);
            if (empty($result)) {
                continue;
            }

            $info = array(
                'cover_image_cn'      => $attr['thumbnail-url-base'] ?: '',
                'description_cn'      => $attr['long-description'] ?: '',
                'language_support_cn' => is_numeric($index) ? str_replace('版', '',$attr['skus'][$index]['name']) : '',
                'update_time'         => time(),
                'rating_score'        => $attr['star-rating']['score'] ?: 0,
                'rating_total'        => $attr['star-rating']['total'] ?: 0,
            );

            if ((int)$result['is_final'] == 0) {
                $name = $attr['name'] ?: '';
                $info['name_cn'] = $name;
            }

            if (!empty($info['cover_image_cn'])) {
                $info['cover_image_cn'] = str_replace('https://store.playstation.com', '', $info['cover_image_cn']);
            }
            $info['description_cn'] = strip_tags($info['description_cn'], '<br>');
            $info['description_cn'] = preg_replace('/<br\\s*?\/??>/i', chr(13) . chr(10), $info['description_cn']);
            $db->update($info, $where);

            echo "商品 {$item['id']} 中文字段更新完成 $i";
            echo "\r\n";
            $i++;
        }

        $date = date('Y-m-d H:i:s', time());
        echo "{$date} 脚本处理完毕";
    }

    /**
     * 标记主商品
     */
    public function main()
    {
        $db            = pdo();
        $db->tableName = 'goods';
        $sql = "select np_title_id from goods where parent_np_title_id = '' or np_title_id = parent_np_title_id group by np_title_id order by id desc";
        $list = $db->query($sql);

        if (empty($list)) {
            return false;
        }

        $service = s('Common');
        $i       = 1;
        foreach ($list as $info) {
            if (empty($info['np_title_id'])) {
                continue;
            }
            $url      = 'https://store.playstation.com/valkyrie-api/zh/hk/19/resolve/' . $info['np_title_id'];
            $response = $service->curl($url);
            if ($service->hasError()) {
                echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();
                continue;
            }
            $data = json_decode($response, true);
            if (empty($data['data']['relationships']['children']['data'])) {
                $url      = 'https://store.playstation.com/valkyrie-api/zh/HK/19/resolve/' . $info['np_title_id'];
                $response = $service->curl($url);
                if ($service->hasError()) {
                    echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                    $service->flushError();
                    continue;
                }

                $data     = json_decode($response, true);
                if (empty($data['data']['relationships']['children']['data'])) {
                    echo "根据 {$info['np_title_id']} 获取主商品数据失败 \r\n";
                    continue;
                }
            }

            $item = $data['data']['relationships']['children']['data'][0];
            if ($item['type'] == 'game') {
                $main_goods_id = $item['id'];
            }

            if (empty($main_goods_id)) {
                echo "获取到的主商品id为空 \r\n";
                continue;
            }

            $where['np_title_id'] = $info['np_title_id'];
            $update['is_main'] = 0;
            $db->update($update, $where);

            $where['goods_id'] = $main_goods_id;
            $where['np_title_id'] = $info['np_title_id'];
            $update['is_main'] = 1;

            $db->update($update, $where);

            echo "商品 {$main_goods_id} 更新为主商品 $i";
            echo "\r\n";
            $i++;
        }

        $date = date('Y-m-d H:i:s', time());
        echo "{$date} 脚本处理完毕";
    }

    protected function getLastPrice($goods_id)
    {
        $db = pdo();
        $db->tableName = 'goods_price_history';
        $where['goods_id'] = $goods_id;
        $info = $db->find($where, '*', 'date desc');

        return $info;
    }

    public function failPrice()
    {
        $service = s('Common');
        $redis = r('psn_redis');
        $fail_list_key = redis_key('price_update_fail_list');
        $length = $redis->llen($fail_list_key);
        if (empty($length)) {
//            echo "没有需要同步的的商品 \r\n";
            return;
        }
        $goods_id = $redis->rPop($fail_list_key);
        $url      = 'https://store.playstation.com/valkyrie-api/en/hk/19/resolve/' . $goods_id;
        $response = $service->curl($url);

        $data = json_decode($response, true);
        if (empty($data['included'])) {
            $url      = 'https://store.playstation.com/valkyrie-api/en/HK/19/resolve/' . $goods_id;
            $response = $service->curl($url);
            $data     = json_decode($response, true);
            if (empty($data['included'])) {
                echo "商品 {$goods_id} 更新价格失败 \r\n";
                $fail_list_key = redis_key('price_update_fail_list');
                $redis->lpush($fail_list_key, $goods_id);
                return;
            }
        }

        $id = $this->handleData($data, $goods_id);
        $history_tips = $id ? "历史价格id新增至 {$id}" : '';
        echo "商品价格 {$goods_id} 更新完成 {$history_tips} \r\n";
    }

    /**
     * 降价通知 模板消息 【弃用】
     */
    /**
    public function reducePriceNotice()
    {
        $date = strtotime(date('Y-m-d', time()));
        $redis = r('psn_redis');
        $db = pdo();
        $sql = "select * from goods_price_history where date = {$date} and start_date > 0 and (discount > 0 or plus_discount > 0)";
        $list = $db->query($sql);
        if (empty($list)) {
            log::n('discount_goods_is_empty');
            echo date('Y-m-d H:i:s') . " 暂无发现降价商品 \r\n";
            return false;
        }
        $service = s('MiniProgram', 2);
        $goods_service = s('Goods');
        $goods_id_arr = array_column($list, 'goods_id');
        $goods_id_str = implode("','", $goods_id_arr);
        $goods_list = array();
        foreach ($list as $info) {
            $goods_list[$info['goods_id']] = $info;
        }

        $goods_info_arr = $goods_service->getGoodsInfo($goods_id_arr);
        $sql = "select * from follow where goods_id in ('{$goods_id_str}') and status = 1 order by open_id desc";
        $follow_list = $db->query($sql);

        $push_list = array();
        foreach ($follow_list as $follow_info) {
            $push_list[$follow_info['open_id']][] = $follow_info['goods_id'];
        }
        foreach ($push_list as $open_id => $info) {
            $redis_key = redis_key('reduce_price_notice_lock', $open_id);
            $lock = $redis->get($redis_key);
            if ($lock) {
                echo "reduce_price_notice_lock:{$open_id} \r\n";
                continue;
            }
            $content['touser'] = $open_id;
            $content['template_id'] = 'BOQSUtVluGMal68HJAh6XZlO2X7kxg8_V8WCo_VGCkA';
            $form_id = $service->getFormId($open_id);

            if ($service->hasError()) {
                echo ("get_form_id_fail: $open_id" . json_encode($service->getError()) . "\r\n");
                log::w("get_form_id_fail: $open_id " . json_encode($service->getError()));
                $service->flushError();
                continue;
            }
            $content['form_id'] = $form_id['form_id'];
            $game_str = '';

            if (count($info) > 1) {
                $top = array_splice($info, 0, 3);
                foreach ($top as $key => $goods_id) {
                    $game_str .= $key < count($top)-1 ? "《{$goods_info_arr[$goods_id]['name_cn']}》、" : "《{$goods_info_arr[$goods_id]['name_cn']}》";
                }
                $content['data'] = array(
                    'keyword1' => array(
                        'value' => count($info) <= 3 ? "您订阅的游戏{$game_str}发生变化" : "您订阅的游戏{$game_str}等发生变化",
                    ),
                    'keyword2' => array(
                        'value' => '于' . date('Y-m-d') . '开始',
                    ),
                    'keyword3' => array(
                        'value' => "点击查看更多详情" ,
                    ),
                );
                $content['page'] = 'pages/myfollow/myfollow?open_id=' . $open_id;
            } else {
                $goods_id = $info[0];
                $end_date = $goods_list[$goods_id]['end_date'] ? date('Y-m-d', $goods_list[$goods_id]['end_date']) : '';
                $end_date_str = $end_date ? "截止至{$end_date}" : "暂无说明截止时间";
                $content['data'] = array(
                    'keyword1' => array(
                        'value' => "您订阅的游戏 《{$goods_info_arr[$goods_id]['name_cn']}》发生变化",
                    ),
                    'keyword2' => array(
                        'value' => $end_date_str,
                    ),
                    'keyword3' => array(
                        'value' => "-{$goods_list[$goods_id]['plus_discount']}%" ,
                    ),
                );
                $content['page'] = 'pages/detail/detail?goods_id=' . $goods_id;
            }

            $json = json_encode($content);
            $service->sendMessage($json);
            if ($service->hasError()) {
                echo "send_message_fail: {$open_id} {$json} \r\n" .  json_encode($service->getError());
                log::w("send_message_fail:" . json_encode($service->getError()) . $json);
                $service->flushError();
                continue;
            }
            log::i("send_message_success: {$open_id} {$json}");
            echo "send_message_success: {$open_id} {$json} \r\n";

            $expire_time = strtotime(date('Y-m-d 12:00:00',time() + 86400));
            $redis->set($redis_key, 1);
            $redis->expireAt($redis_key, $expire_time);
        }

    }
    **/

    public function reducePriceNotice()
    {
        $date = strtotime(date('Y-m-d', time()));
//        $date = 1569945600;
        $redis = r('psn_redis');
        $db    = pdo();
        $sql   = "select * from goods_price_history where date = {$date} and start_date > 0 and (discount > 0 or plus_discount > 0)";
        $list  = $db->query($sql);
        if (empty($list)) {
            log::n('discount_goods_is_empty');
            echo date('Y-m-d H:i:s') . " 暂无发现降价商品 \r\n";
            return false;
        }

        $service       = s('MiniProgram', 2);
        $tools_service = s('Tools');
        $goods_service = s('Goods');
        $goods_id_arr  = array_column($list, 'goods_id');
        $goods_id_str  = implode("','", $goods_id_arr);
        $goods_list    = array();
        foreach ($list as $info) {
            $goods_list[$info['goods_id']] = $info;
        }

        $goods_info_arr = $goods_service->getGoodsInfo($goods_id_arr);
        $sql            = "select a.user_id,a.goods_id,b.open_id from follow a left join open_id b on a.user_id = b.user_id where a.goods_id in ('{$goods_id_str}') and a.status = 1 and b.appcode = 2 order by user_id desc";
        $follow_list    = $db->query($sql);
        $push_list      = array();
        foreach ($follow_list as $follow_info) {
            $push_list[$follow_info['open_id']][] = $follow_info['goods_id'];
        }
        foreach ($push_list as $open_id => $info) {
            $redis_key = redis_key('reduce_price_notice_lock', $open_id);
            $allow = $redis->setnx($redis_key, 1);
            $expire_time = strtotime(date('Y-m-d 12:00:00', time() + 86400));
            $redis->expireAt($redis_key, $expire_time);

            if ($allow === false) {
                echo "reduce_price_notice_lock:{$open_id} \r\n";
                continue;
            }
            $content['touser']            = $open_id;
            $content['template_id']       = 'vL0jQU9I1k6QJwO0mY3Y3X5DNkj-3FAN2N3HFTucrIU';
            $content['miniprogram_state'] = 'formal';       //developer为开发版；trial为体验版；formal为正式版；默认为正式版

            if (count($info) > 1) {
                $goods_id        = $info[0];
                $game_str        = "《{$goods_info_arr[$goods_id]['name_cn']}》";
                $content['data'] = array(
                    'thing1' => array(
                        'value' => '您订阅的多个游戏发生变化',
                    ),
                    'time2' => array(
                        'value' => date('Y-m-d'),
                    ),
                    'thing3' => array(
                        'value' => mb_substr($game_str, 0, 16) . '...',
                    ),
                );
                $content['page'] = 'pages/myfollow/myfollow?open_id=' . $open_id;
            } else {
                $goods_id        = $info[0];
                $game_str        = "《{$goods_info_arr[$goods_id]['name_cn']}》";
                $content['data'] = array(
                    'thing1' => array(
                        'value' => "您订阅的游戏发生变化",
                    ),
                    'time2' => array(
                        'value' => date('Y-m-d'),
                    ),
                    'thing3' => array(
                        'value' => mb_strlen($game_str) > 20 ? mb_substr($game_str, 0, 16) . '...' : $game_str,
                    ),
                );
                $content['page'] = 'pages/detail/detail?goods_id=' . $goods_id;
            }

            $json = json_encode($content, 256);
            $service->sendSubscribeMessage($json);
            if ($service->hasError()) {
                echo "send_message_fail: {$open_id} {$json} \r\n" . json_encode($service->getError());
                log::w("send_message_fail:" . json_encode($service->getError()) . $json);
                $service->flushError();
                continue;
            } else {
                log::i("send_message_success: {$open_id} {$json}");
                echo "send_message_success: {$open_id} {$json} \r\n";
            }
        }

        //推送至app
        $app_push_list = array();
        foreach ($follow_list as $follow_info) {
            $app_push_list[$follow_info['user_id']][] = $follow_info['goods_id'];
        }

        foreach ($app_push_list as $user_id => $info) {
            $redis_key = redis_key('reduce_price_notice_lock', $user_id);
            $allow = $redis->setnx($redis_key, 1);
            $expire_time = strtotime(date('Y-m-d 12:00:00', time() + 86400));
            $redis->expireAt($redis_key, $expire_time);

            if ($allow === false) {
                echo "reduce_price_notice_lock:{$user_id} \r\n";
                continue;
            }

            $data = array(
                array(
                    'user_ids' => $user_id,
                    'alert'    => '您好，您关注的商品已经降价',
                    'content'  => array(
                        'goods_id' => $info[0],
                        'con_type' => 'goods',
                    )
                )
            );

            $json = json_encode($data, 256);
            $tools_service->sendAppPushNotice($json);
            if ($tools_service->hasError()) {
                echo "send_app_push_fail: {$user_id} {$json} \r\n" . json_encode($tools_service->getError());
                log::w("send_app_push_fail:" . json_encode($tools_service->getError()) . $json);
                $service->flushError();
                continue;
            } else {
                log::i("send_app_push_success: {$user_id} {$json}");
                echo "send_app_push_success: {$user_id} {$json} \r\n";
            }
        }
    }

    public function handleDisplayName()
    {
        $json = require VERSION_PATH .'/cli/json.php';
        $list = json_decode($json, true);
        if (!is_array($list)) {
            echo 'data_invalid';
        }

        $db = pdo();
        $db->tableName = 'goods';
        foreach ($list as $info) {
            if (empty($info['goods_id'])) {
                continue;
            }
            $where['goods_id'] = $info['goods_id'];
//            $data['name'] = $info['name'];
            $data['name_cn'] = $info['name_cn'];
            $data['is_final'] = 1;
            $data['update_time'] = time();
            try {
                echo "更新商品名称成功：{$info['goods_id']} {$info['name_cn']} \r\n";
                $db->update($data, $where);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    public function exportHotGame()
    {
        $db = pdo();
        $db->tableName = 'goods';
        $where = "is_final = 1";
        $list = $db->findAll($where, '*', 'rating_total desc');
        $data = array();
        foreach ($list as $info) {
            $temp['goods_id'] = $info['goods_id'];
            $temp['name'] = $info['name'];
            $temp['name_cn'] = $info['name_cn'];
            $data[] = $temp;
        }
    }

    public function game()
    {
        $db = pdo();
        $page = 1;
        $limit = 100;
        while (true) {
            $start = ($page - 1) * $limit;
            $limit_str = " limit {$start}, {$limit}";
            $sql = "select *, min(`release_date`) as min_release_date from (select * from goods where np_communication_id <> '' order by is_main desc, status desc, rating_total desc) as t  group by np_communication_id order by id desc";
            $sql = $sql . $limit_str;
            $list = $db->query($sql);
            if (empty($list)) {
                break;
            }
            $this->initGameByNpCommunicationId($list);
            if ($this->hasError()) {
                break;
            }
            $page++;
        }

        $page = 1;
        $limit = 100;
        while (true) {
            $start = ($page - 1) * $limit;
            $limit_str = " limit {$start}, {$limit}";
            $sql = "select *, min(`release_date`) as min_release_date from (select * from goods where np_communication_id = '' and (`parent_np_title_id` = '' or `parent_np_title_id` = `np_title_id`) order by is_main desc, status desc, rating_total desc) as t group by np_title_id order by id desc ";
            $sql = $sql . $limit_str;
            $list = $db->query($sql);
            if (empty($list)) {
                break;
            }
            $this->initGameByNpTitleId($list);
            if ($this->hasError()) {
                break;
            }
            $page++;
        }

    }


    public function generateGameId()
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('game_id_list');
        $i = 1;

        while ($i <= 100) {
            $game_id = mt_rand(100001, 199999);
            $is_member = $redis->sIsMember($redis_key, $game_id);
            if (!empty($is_member)) {
                $i++;
            } else {
                $redis->sAdd($redis_key, $game_id);
                return $game_id;
            }

            if ($i >= 100) {
                return $this->setError('generate_game_id_fail', '无法生成合适的game_id, 已重试100次');
            }
        }
    }

    public function gameDiscount()
    {
        $db = pdo();
        $db->tableName = 'game';
        $list = $db->findAll('1=1', '*', 'id asc');

        if (empty($list)) {
            return false;
        }

        foreach ($list as $game) {
            $sql = "select a.goods_id from (select * from goods where game_id = {$game['game_id']}) as a left join goods_price as b on a.goods_id = b.goods_id where (b.`discount` > 0 || b.`plus_discount` > 0) and b.`status` >= 1";
            $result = $db->query($sql);
            if (empty($result)) {
                $data['is_discount'] = 0;
                $where['game_id'] = $game['game_id'];
            } else {
                $data['is_discount'] = 1;
                $where['game_id'] = $game['game_id'];
            }
            $db->update($data, $where);

            echo "游戏资料id {$game['game_id']} 折扣数据更新成功 {$data['is_discount']} \r\n";
        }

        echo "任务处理完成 \r\n";
    }

    public function media()
    {
        $db            = pdo();
        $db->tableName = 'goods';
        $last_id       = 0;
        $list          = $db->findAll("id > {$last_id}", '*', 'id desc');
        if (empty($list)) {
            return false;
        }
        $service = s('Common');
        $i       = 1;
        foreach ($list as $info) {
            $goods_id = $info['goods_id'];
            if (empty($goods_id) || in_array($goods_id, c('exception_goods_id_for_media'))) {
                continue;
            }
            $url      = 'https://store.playstation.com/valkyrie-api/en/us/19/resolve/' . $goods_id;
            $response = $service->curl($url);
            if ($service->hasError()) {
                echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();

                $url = 'https://store.playstation.com/valkyrie-api/en/US/19/resolve/' . $goods_id;
                $response = $service->curl($url);
                if ($service->hasError()) {
                    echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                    $service->flushError();
                    continue;
                }
            }
            $data = json_decode($response, true);
            if (empty($data['included'])) {
                echo "商品 {$goods_id} 远端媒体数据更新失败 \r\n";
                var_dump($response);
                continue;
            }

            $item = $data['included'][0];
            $attr = $item['attributes'];

            $media = array(
                'preview'            => !empty($attr['media-list']['preview']) ? str_replace('https:\/\/apollo2.dl.playstation.net','',json_encode($attr['media-list']['preview'])) : '',
                'screenshots'        => !empty($attr['media-list']['screenshots']) ? str_replace('https:\/\/apollo2.dl.playstation.net','',json_encode($attr['media-list']['screenshots'])) : '',
            );

//            if (!empty($info['preview']) && !empty($info['screenshots'])) {
//                echo "商品 {$info['goods_id']} 无需更新数据 \r\n";
//                continue;
//            }

            $update = array();
            !empty($media['preview']) && $update['preview'] = $media['preview'];
            !empty($media['screenshots']) && $update['screenshots'] = $media['screenshots'];

            if (empty($update)) {
                echo "商品 {$goods_id} 无可用数据更新 \r\n";
                continue;
            }
            $db->update($update, array('goods_id' => $goods_id));

            echo "商品 {$info['goods_id']} 媒体数据更新完成 $i \r\n";
            $i++;
        }

        $date = date('Y-m-d H:i:s', time());
        echo "{$date} 脚本处理完毕";
    }

    protected function initGameByNpTitleId($list)
    {
        if (empty($list)) {
            return $this->setError('param_list_is_empty');
        }

        $db = pdo();
        $db->tableName = 'game';

        foreach ($list as $goods)
        {
            //            $goods['name'] = str_replace('™', '', $goods['name']);
            //            $goods['name'] = str_replace('®', '', $goods['name']);
            $game_id = 0;
            $is_vr_support = 0;
            if ($goods['ps_vr']) {
                $is_vr_support = 1;
            }
            $game = array(
                'np_title_id' => $goods['np_title_id'],
                'np_communication_id' => $goods['np_communication_id'],
                'main_goods_id' => $goods['goods_id'],
                'origin_name' => $goods['name'],
                'display_name' => $goods['name_cn'],
                'other_name' => '',
                'cover_image' => $goods['cover_image_cn'],
                'other_platform' => '',
                'developer' => $goods['developer'],
                'publisher' => $goods['publisher'],
                'franchises' => '',
                'genres' => $goods['genres'],
                'release_date' => $goods['min_release_date'],
                'description' => $goods['description_cn'],
                'language_support' => $goods['language_support_cn'],
                'is_chinese_support' => strpos($goods['language_support_cn'], '中') === false ? 0 : 1,
                'is_vr_support' => $is_vr_support,
                'screenshots' => $goods['screenshots'],
                'videos' => $goods['preview'],
                'rating_total' => $goods['rating_total'],
                'rating_score' => $goods['rating_score'],
                'origin' => 2,
                'status' => 1,
                'create_time' => time(),
            );

            $db->startTrans();
            try {
                $where = "np_title_id = '{$goods['np_title_id']}' and status = 1";
                $info = $db->find($where);
                if (empty($info)) {
                    $game_id = $this->generateGameId();
                    if ($this->hasError()) {
                        log::e('generate_game_id_fial:' . json_encode($this->getError()));
                        continue;
                    }
                    $game['game_id'] = $game_id;
                    $db->insert($game);
                } else {
                    $game_id = $info['game_id'];
                    $data['main_goods_id'] = $game['main_goods_id'];
                    $data['np_communication_id'] = $game['np_communication_id'];
                    $data['origin'] = 2;
                    $data['is_vr_support'] = $is_vr_support;
                    $data['cover_image'] = $game['cover_image'];
//                    $data['language_support'] = $game['language_support'];
//                    $data['is_chinese_support'] = $game['is_chinese_support'];
                    $data['rating_total'] = $game['rating_total'];
                    $data['rating_score'] = $game['rating_score'];
                    $data['screenshots'] = $game['screenshots'];
                    $data['videos'] = $game['videos'];
                    $data['update_time'] = time();

                    $db->update($data, $where);
                }
                $sql = "update goods set game_id = ? where np_title_id = ? or parent_np_title_id = ?";
                $db->exec($sql, $game_id, $goods['np_title_id'], $goods['np_title_id']);
            } catch (Exception $e) {
                $db->rollBackTrans();
                echo "写入数据库出现异常：{$e->getMessage()}";
                continue;
            }
            $db->commitTrans();

            echo "游戏资料写入成功：{$game_id} {$goods['name']} \r\n";
        }

    }

    protected function initGameByNpCommunicationId($list)
    {
        if (empty($list)) {
            return $this->setError('param_list_is_empty');
        }

        $redis = r('psn_redis');
        $db = pdo();
        $db->tableName = 'game';

        foreach ($list as $goods)
        {
            //            $goods['name'] = str_replace('™', '', $goods['name']);
            //            $goods['name'] = str_replace('®', '', $goods['name']);
            $game_id = 0;
            $is_vr_support = 0;
            if ($goods['ps_vr']) {
                $is_vr_support = 1;
            }
            $game = array(
                'np_title_id' => $goods['np_title_id'],
                'np_communication_id' => $goods['np_communication_id'],
                'main_goods_id' => $goods['goods_id'],
                'origin_name' => $goods['name'],
                'display_name' => $goods['name_cn'],
                'other_name' => '',
                'cover_image' => $goods['cover_image_cn'],
                'other_platform' => '',
                'developer' => $goods['developer'],
                'publisher' => $goods['publisher'],
                'franchises' => '',
                'genres' => $goods['genres'],
                'release_date' => $goods['min_release_date'],
                'description' => $goods['description_cn'],
                'language_support' => $goods['language_support_cn'],
                'is_chinese_support' => strpos($goods['language_support_cn'], '中') === false ? 0 : 1,
                'is_vr_support' => $is_vr_support,
                'screenshots' => $goods['screenshots'],
                'videos' => $goods['preview'],
                'rating_total' => $goods['rating_total'],
                'rating_score' => $goods['rating_score'],
                'status' => 1,
                'origin' => 1,
                'create_time' => time(),
            );

            $db->startTrans();
            try {
                $where = "(np_title_id = '{$goods['np_title_id']}' or np_communication_id = '{$goods['np_communication_id']}') and status = 1";
                $info = $db->find($where);
                if (empty($info)) {
                    $game_id = $this->generateGameId();
                    if ($this->hasError()) {
                        log::e('generate_game_id_fial:' . json_encode($this->getError()));
                        continue;
                    }
                    $game['game_id'] = $game_id;
                    $db->insert($game);
                } else {
                    $game_id = $info['game_id'];
                    $data['origin_name'] = $game['origin_name'];
                    $data['np_title_id'] = $game['np_title_id'];
                    $data['np_communication_id'] = $game['np_communication_id'];
                    $data['main_goods_id'] = $game['main_goods_id'];
                    $data['is_vr_support'] = $is_vr_support;
                    $data['origin'] = 1;
                    $data['cover_image'] = $game['cover_image'];
//                    $data['language_support'] = $game['language_support'];
//                    $data['is_chinese_support'] = $game['is_chinese_support'];
                    $data['rating_total'] = $game['rating_total'];
                    $data['rating_score'] = $game['rating_score'];
                    $data['screenshots'] = $game['screenshots'];
                    $data['videos'] = $game['videos'];
                    $data['update_time'] = time();

                    $db->update($data, $where);
                }
                $sql = "update goods set game_id = ? where np_communication_id = ?";
                $db->exec($sql, $game_id, $goods['np_communication_id']);
            } catch (Exception $e) {
                $db->rollBackTrans();
                echo "写入数据库出现异常：{$e->getMessage()}";
                continue;
            }
            $db->commitTrans();
            $redis_key = redis_key('relation_game_trophy', $game_id);
            $redis->set($redis_key, $goods['np_communication_id']);
            $redis_key = redis_key('relation_trophy_game', $goods['np_communication_id']);
            $redis->set($redis_key, $game_id);
            echo "游戏资料写入成功：{$game_id} {$goods['name']} \r\n";
        }

    }


}
