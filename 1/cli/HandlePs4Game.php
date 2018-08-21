<?php
class HandlePs4Game
{
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

    public function goods()
    {
        $db            = pdo();
        $db->tableName = 'game_code';
        $last_id       = 0;
        $list          = $db->findAll("id > {$last_id}", '*', 'id asc');
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
            $data     = json_decode($response, true);

            if (empty($data['included'])) {
                $url      = 'https://store.playstation.com/valkyrie-api/en/HK/19/resolve/' . $info['store_game_code'];
                $response = $service->curl($url);
                $data     = json_decode($response, true);
                if (empty($data['included'])) {
                    echo "商品 {$info['store_game_code']} 获取数据失败 \r\n";
                    continue;
                }
            }

            $item = $data['included'][0];
            $attr = $item['attributes'];

            $product_id_arr = explode('-', $info['store_game_code']);
            $np_title_id    = $product_id_arr[1];

            $db->tableName     = 'goods';
            $where['goods_id'] = $item['id'];
            $result            = $db->find($where);

            $info = array(
                'goods_id'         => $item['id'] ?: '',
                'np_title_id'      => $np_title_id ?: '',
                'name'             => $attr['name'] ?: '',
                'cover_image'      => $attr['thumbnail-url-base'] ?: '',
                'description'      => $attr['long-description'] ?: '',
                'rating_score'     => $attr['star-rating']['score'] ?: 0,
                'rating_total'     => $attr['star-rating']['total'] ?: 0,
                'preview'          => !empty($attr['media-list']['preview']) ? json_encode($attr['media-list']['preview']) : '',
                'screenshots'      => !empty($attr['media-list']['screenshots']) ? json_encode($attr['media-list']['screenshots']) : '',
                'release_date'     => $attr['release-date']? strtotime($attr['release-date']) : 0,
                'publisher'        => $attr['provider-name'] ?: '',
                'developer'        => '',
                'file_size'        => $attr['file-size']['value'] ?: 0,
                'file_size_unit'   => $attr['file-size']['unit'] ?: '',
                'genres'           => $attr['genres'] ? implode(',', $attr['genres']) : '',
                'language_support' => $attr['skus'][0]['name'],
            );

            $info['description'] = strip_tags($info['description'], '<br>');
            $info['description'] = preg_replace('/<br\\s*?\/??>/i', chr(13) . chr(10), $info['description']);

            if (empty($result)) {
                $info['create_time'] = time();
                $db->insert($info);
            } else {
                $info['update_time'] = time();
                $condition['id']     = $result['id'];
                $db->update($info, $condition);
            }

            echo "商品 {$item['id']} 入库完成 $i";
            echo "\r\n";
            $i++;
        }

        echo "脚本处理完毕";
    }

    public function price()
    {
        $db            = pdo();
        $redis         = r('psn_redis');
        $db->tableName = 'game_code';
        $last_id       = 0;
        $list          = $db->findAll("id > {$last_id}", '*', 'id asc');
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

            $data = json_decode($response, true);
            if (empty($data['included'])) {
                $url      = 'https://store.playstation.com/valkyrie-api/en/HK/19/resolve/' . $info['store_game_code'];
                $response = $service->curl($url);
                $data     = json_decode($response, true);
                if (empty($data['included'])) {
                    echo "商品 {$info['store_game_code']} 更新价格失败 \r\n";
                    $fail_list_key = redis_key('price_update_fail_list');
                    $redis->lpush($fail_list_key, $info['store_game_code']);
                    continue;
                }
            }

            $result = $this->handleData($data);
            $result && $id = $result;
            echo "商品价格 {$info['store_game_code']} 更新完成 $i";
            echo "\r\n";
            $i++;
        }

        $today = date('Y-m-d', time());
        $history_tips = $id ? "历史价格id新增至 {$id}" : '';
        echo "{$today} 脚本处理完毕 {$history_tips} \r\n";
    }

    public function language()
    {
        $db            = pdo();
        $db->tableName = 'game_code';
        $last_id       = 1139;
        $list          = $db->findAll("id > {$last_id}", '*', 'id asc');
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
            $data = json_decode($response, true);
            if (empty($data['included'])) {
                $url      = 'https://store.playstation.com/valkyrie-api/zh/HK/19/resolve/' . $info['store_game_code'];
                $response = $service->curl($url);
                $data     = json_decode($response, true);
                if (empty($data['included'])) {
                    echo "商品 {$info['store_game_code']} 中文更新失败 \r\n";
                    continue;
                }
            }

            $item = $data['included'][0];
            $attr = $item['attributes'];

            $db->tableName     = 'goods';
            $where['goods_id'] = $item['id'];

            $info = array(
                'name_cn'             => $attr['name'] ?: '',
                'cover_image_cn'      => $attr['thumbnail-url-base'] ?: '',
                'description_cn'      => $attr['long-description'] ?: '',
                'language_support_cn' => str_replace('版', '',$attr['skus'][0]['name']),
                'update_time'         => time(),
            );

            $info['description_cn'] = strip_tags($info['description_cn'], '<br>');
            $info['description_cn'] = preg_replace('/<br\\s*?\/??>/i', chr(13) . chr(10), $info['description_cn']);
            $db->update($info, $where);

            echo "商品 {$item['id']} 中文字段更新完成 $i";
            echo "\r\n";
            $i++;
        }

        echo "脚本处理完毕";
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

        $id = $this->handleData($data);
        $history_tips = $id ? "历史价格id新增至 {$id}" : '';
        echo "商品价格 {$goods_id} 更新完成 {$history_tips} \r\n";
    }

    public function handleData($data)
    {
        $item = $data['included'][0];
        $attr = $item['attributes'];

        $db = pdo();
        $db->tableName     = 'goods_price';
        $where['goods_id'] = $item['id'];
        $result            = $db->find($where);

        $status            = empty($attr['skus'][0]['prices']) ? 0 : 1;
        $status            = empty($attr['skus'][0]['is-preorder']) ? $status : 2;
        $origin_price      = $attr['skus'][0]['prices']['non-plus-user']['strikethrough-price']['value'];
        $sale_price        = $attr['skus'][0]['prices']['non-plus-user']['actual-price']['value'];
        $discount          = $attr['skus'][0]['prices']['non-plus-user']['discount-percentage'];
        $plus_origin_price = $attr['skus'][0]['prices']['plus-user']['strikethrough-price']['value'];
        $plus_sale_price   = $attr['skus'][0]['prices']['plus-user']['actual-price']['value'];
        $plus_discount     = $attr['skus'][0]['prices']['plus-user']['discount-percentage'];
        $start_date        = $attr['skus'][0]['prices']['non-plus-user']['availability']['start-date'];
        $end_date          = $attr['skus'][0]['prices']['non-plus-user']['availability']['end-date'];

        $info = array(
            'goods_id'          => $item['id'] ?: '',
            'origin_price'      => is_numeric($origin_price) ? $origin_price : null,
            'sale_price'        => is_numeric($sale_price) ? $sale_price : null,
            'discount'          => is_numeric($discount) ? $discount : null,
            'plus_origin_price' => is_numeric($plus_origin_price) ? $plus_origin_price : null,
            'plus_sale_price'   => is_numeric($plus_sale_price) ? $plus_sale_price : null,
            'plus_discount'     => is_numeric($plus_discount) ? $plus_discount : null,
            'start_date'        => $start_date ? strtotime($start_date) : 0,
            'end_date'          => $end_date ? strtotime($end_date) : 0,
            'status'            => $status,
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
                'plus_price' => $info['plus_sale_price'],
                'start_date' => $info['start_date'],
                'end_date' => $info['end_date'],
                'date' => strtotime(date('Y-m-d', time())),
                'create_time' => time(),
            );
            $id = $db->insert($data);
        } else {
            if ($sale_price != $result['sale_price'] || $plus_sale_price != $result['plus_sale_price']) {
                //判断价格是否新低
                if (is_numeric($sale_price) && $sale_price == $result['lowest_price']) {
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
                if (is_numeric($plus_sale_price) && $plus_sale_price == $result['plus_lowest_price']) {
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
                    'plus_price' => $info['plus_sale_price'],
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

        $db->tableName = 'goods';
        $goods['status'] = $status;
        $goods['update_time'] = time();
        $db->update($goods, $where);

        unset($data);
        unset($info);
        unset($result);

        return $id;
    }
}
