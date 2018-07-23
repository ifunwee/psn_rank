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
        $list          = $db->findAll('1=1', '*', 'id asc');
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

            $data = json_decode($response, true);
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
                'release_date'     => $attr['release-date']? strtotime($attr['release-date']) : '',
                'publisher'        => $attr['provider-name'] ?: '',
                'developer'        => '',
            );

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
        $db->tableName = 'game_code';
        $list          = $db->findAll('1=1', '*', 'id asc');
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

            $data = json_decode($response, true);
            $item = $data['included'][0];
            $attr = $item['attributes'];

            $db->tableName     = 'goods_price';
            $where['goods_id'] = $item['id'];
            $result            = $db->find($where);

            $origin_price = $attr['skus'][0]['prices']['non-plus-user']['strikethrough-price']['value'];
            $sale_price = $attr['skus'][0]['prices']['non-plus-user']['actual-price']['value'];
            $discount = $attr['skus'][0]['prices']['non-plus-user']['discount-percentage'];
            $plus_origin_price = $attr['skus'][0]['prices']['plus-user']['strikethrough-price']['value'];
            $plus_sale_price = $attr['skus'][0]['prices']['plus-user']['actual-price']['value'];
            $plus_discount = $attr['skus'][0]['prices']['plus-user']['discount-percentage'];
            $start_date = $attr['skus'][0]['prices']['non-plus-user']['availability']['start-date'];
            $end_date = $attr['skus'][0]['prices']['non-plus-user']['availability']['end-date'];

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
            );

            if (empty($result)) {
                $info['create_time'] = time();
                $db->insert($info);
            } else {
                $info['update_time'] = time();
                $condition['id']     = $result['id'];
                $db->update($info, $condition);
            }

            echo "商品价格 {$item['id']} 更新完成 $i";
            echo "\r\n";
            $i++;
        }

        echo "脚本处理完毕";
    }

    public function language()
    {
        $db            = pdo();
        $db->tableName = 'game_code';
        $list          = $db->findAll('1=1', '*', 'id asc');
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
            $item = $data['included'][0];
            $attr = $item['attributes'];

            $db->tableName     = 'goods';
            $where['goods_id'] = $item['id'];

            $info = array(
                'name_cn'        => $attr['name'] ?: '',
                'cover_image_cn' => $attr['thumbnail-url-base'] ?: '',
                'description_cn' => $attr['long-description'] ?: '',
                'release_date'   => $attr['release-date'] ? strtotime($attr['release-date']) : '',
                'update_time'    => time(),
            );

            $info['update_time'] = time();
            $db->update($info, $where);

            echo "商品 {$item['id']} 中文字段更新完成 $i";
            echo "\r\n";
            $i++;
        }

        echo "脚本处理完毕";
    }
}
