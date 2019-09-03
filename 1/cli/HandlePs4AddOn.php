<?php
/**
 * Created by PhpStorm.
 * User: funwee
 * Date: 2019/9/1
 * Time: 7:01 PM
 */

class HandlePs4AddOn extends BaseService
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

    public function addition()
    {
        $service = s('Common');
        $db      = pdo();
        $i       = 0;
        $size    = 100;
        while (true) {
            $start   = $i * $size;
            $url      = "https://store.playstation.com/valkyrie-api/en/HK/999/container/STORE-MSF86012-ALLADDONS?platform=ps4&size={$size}&bucket=games&start={$start}";
            $response = $service->curl($url);
            if ($service->hasError()) {
                echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();
                continue;
            }
            $data = json_decode($response, true);
            if (empty($data['included'])) {
                echo "获取第{$i}页数据失败 \r\n";
                var_dump($data);
                break;
            }

            foreach ($data['included'] as $item) {
                if ($item['type'] !== 'game-related') {
                    continue;
                }
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

                $product_id_arr = explode('-', $item['id']);
                $np_title_id    = $product_id_arr[1];

                $parent_product_id_arr = array();
                $parent_goods_id    = $attr['parent']['id'];
                $parent_goods_id && $parent_product_id_arr = explode('-', $parent_goods_id);

                $parent_np_title_id = $parent_product_id_arr[1];

                $db->tableName     = 'addition';
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

                echo "追加内容 {$item['id']} 入库完成";
                echo "\r\n";
            }
            $end = $start + $size;
            echo "第{$end}条数据处理完毕 \r\n";
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
        $service = s('Common');
        $page = 1;
        $limit = 1000;
        $id      = 0;
        $i       = 1;

        while (true) {
            $start     = ($page - 1) * $limit;
            $limit_str = "{$start},{$limit}";
            $db->tableName = 'addition';
            $last_id       = 11754;
            $list      = $db->findAll("id <= {$last_id}", 'goods_id', 'id desc', $limit_str);

            if (empty($list)) {
                break;
            }

            foreach ($list as $info) {
                if (empty($info['goods_id'])) {
                    continue;
                }

                $url      = 'https://store.playstation.com/valkyrie-api/en/hk/19/resolve/' . $info['goods_id'];
                $response = $service->curl($url);

                if ($service->hasError()) {
                    echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                    $service->flushError();

                    $url      = 'https://store.playstation.com/valkyrie-api/en/HK/19/resolve/' . $info['goods_id'];
                    $response = $service->curl($url);
                    if ($service->hasError()) {
                        echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                        $service->flushError();
                        continue;
                    }
                }
                $data = json_decode($response, true);

                $result = $this->handleData($data, $info['goods_id']);
                if ($this->hasError()) {
                    echo "追加内容 {$info['goods_id']} 价格更新失败： {$this->getErrorCode()} \r\n";
                    $this->flushError();
                    continue;
                }
                $result && $id = $result;
                echo "追加内容 {$info['goods_id']} 价格更新完成 $i \r\n";
                $i++;
            }
            $page++;
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
    public function handleData($item, $handle_goods_id)
    {
        $db = pdo();
//        $goods_id = $data['data']['relationships']['children']['data'][0]['id'];
//        if (empty($goods_id) || $handle_goods_id != $goods_id) {
//            $goods['status'] = -1;
//            $goods['update_time'] = time();
//            $db->tableName = 'addition';
//            $db->update($goods, array('goods_id' => $handle_goods_id));
//            $db->tableName = 'addition_price';
//            $db->update($goods, array('goods_id' => $handle_goods_id));
//            return $this->setError('goods_id_is_not_match');
//        }

//        $item = $data['included'][0];
        $attr = $item['attributes'];
        $default_sku_id = $attr['default-sku-id'];
        $index = null;

        if (empty($default_sku_id)) {
            $db->tableName = 'addition';
            $goods['status'] = 0;
            $goods['update_time'] = time();
            $db->update($goods, array('goods_id' => $handle_goods_id));
            $db->tableName = 'addition_price';
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
//        foreach ($data['included'] as $info) {
//            if ($info['id'] == $default_sku_id) {
//                $sku_price = $info['attributes']['price'];
//                if (empty($info['attributes']['rewards'])) {
//                    continue;
//                }
//                foreach ($info['attributes']['rewards'] as $reward) {
//                    $sku_reward[$reward['discount']] = $reward;
//                    if ($reward['isEAAccess'] === true) {
//                        $is_ea_discount = 1;
//                    }
//                }
//            }
//        }

        $db->tableName = 'addition';
        $goods['status'] = $status;
        $goods['update_time'] = time();
        $db->update($goods, array('goods_id' => $handle_goods_id));

        $db->tableName     = 'addition_price';
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
            $origin_price = 0;
            $discount = 0;
        }

        if ((int)$plus_discount > 0 && $sku_reward[$plus_discount]['isEAAccess'] === true) {
            $plus_sale_price = $sku_price;
            $plus_origin_price = 0;
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
            $db->tableName = 'addition_price';
            $info['lowest_price'] = $info['sale_price'];
            $info['plus_lowest_price'] = $info['plus_sale_price'];
            $info['create_time'] = time();
            $db->insert($info);

            $db->tableName = 'addition_price_history';
            $data = array(
                'goods_id' => $item['id'],
                'price' => $info['sale_price'],
                'plus_price' => $info['plus_sale_price'],
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

                $db->tableName     = 'addition_price';
                $info['update_time'] = time();
                $db->update($info, $where);

                $db->tableName = 'addition_price_history';
                $data = array(
                    'goods_id' => $item['id'],
                    'price' => $info['sale_price'],
                    'plus_price' => $info['plus_sale_price'],
                    'start_date' => $info['start_date'],
                    'end_date' => $info['end_date'],
                    'date' => strtotime(date('Y-m-d', time())),
                    'create_time' => time(),
                );
                $id = $db->insert($data);
            } else {
                $db->tableName     = 'addition_price';
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
        $service = s('Common');
        $db      = pdo();
        $page       = 0;
        $size    = 100;
        while (true) {
            $start   = $page * $size;
            $url      = "https://store.playstation.com/valkyrie-api/en/HK/999/container/STORE-MSF86012-ALLADDONS?platform=ps4&size={$size}&bucket=games&start={$start}";
            $response = $service->curl($url);
            if ($service->hasError()) {
                echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();
                continue;
            }
            $data = json_decode($response, true);
            if (empty($data['included'])) {
                echo "获取第{$page}页数据失败 \r\n";
                var_dump($data);
                break;
            }

            foreach ($data['included'] as $item) {
                if ($item['type'] !== 'game-related') {
                    continue;
                }

                $this->handleData($item, $item['id']);
                if ($this->hasError()) {
                    echo "追加内容 {$item['id']} 价格更新失败： {$this->getErrorCode()} \r\n";
                    $this->flushError();
                }

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

                $db->tableName     = 'addition';
                $where['goods_id'] = $item['id'];
                $result            = $db->find($where);

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

                echo "追加内容 {$item['id']} 语言更新成功\r\n";
            }
            $end = $start + $size;
            echo "第{$end}条数据处理完毕 \r\n";
            $page++;
        }
        $date = date('Y-m-d H:i:s', time());
        echo "{$date} 脚本处理完毕";
    }
}