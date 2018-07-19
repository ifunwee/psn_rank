<?php
class HandlePs4Game
{
    public function hk()
    {
        $db = pdo();
        $redis = r('psn_redis');
        $hk_code_list_key = redis_key('ps4_hk_game_code_list');
        $db->tableName = 'game_code';
        $list = $db->findAll('1=1', '*', 'id asc');
        if (empty($list)) {
            return false;
        }

        foreach ($list as $info) {
            if (empty($info['store_game_code'])) {
                continue;
            }

            $product_id_arr = explode('-', $info['store_game_code']);
            $np_title_id = $product_id_arr[1];
            $redis->zAdd($hk_code_list_key, time(), $np_title_id);
        }

        echo "脚本处理完毕:" . $redis->zCard($hk_code_list_key);
    }

    public function goods()
    {
        $db = pdo();
        $db->tableName = 'game_code';
        $list = $db->findAll('1=1', '*', 'id asc');
        if (empty($list)) {
            return false;
        }

        $service = s('Common');
        $i = 1;
        foreach ($list as $info) {
            if (empty($info['store_game_code'])) {
                continue;
            }
            $url = 'https://store.playstation.com/valkyrie-api/en/hk/19/resolve/' . $info['store_game_code'];
            $response = $service->curl($url);

            $data = json_decode($response, true);
            $item = $data['included'][0];
            $attr = $item['attributes'];
            if ($item['type'] !== 'game') {
                continue;
            }

            $product_id_arr = explode('-', $info['store_game_code']);
            $np_title_id = $product_id_arr[1];

            $db->tableName = 'goods';
            $where['goods_id'] = $item['id'];
            $result = $db->find($where);

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
                'origin_price'     => $attr['skus'][0]['prices']['non-plus-user']['strikethrough-price']['value'] ?: 0,
                'sale_price'       => $attr['skus'][0]['prices']['non-plus-user']['actual-price']['value'] ?: 0,
                'plus_price'       => $attr['skus'][0]['prices']['non-plus-user']['upsell-price']['value'] ?: 0,
                'promo_start_date' => strtotime($attr['skus'][0]['prices']['non-plus-user']['availability']['start-date']) ?: 0,
                'promo_end_date'   => strtotime($attr['skus'][0]['prices']['non-plus-user']['availability']['end-date']) ?: 0,
                'release_date'     => strtotime($attr['release-date']) ?: 0,
                'publisher'        => $attr['provider-name'] ?: '',
                'developer'        => '',
            );

            if (empty($result)) {
                $info['create_time'] = time();
                $db->insert($info);
            } else {
                $info['update_time'] = time();
                $condition['id'] = $result['id'];
                $db->update($info, $condition);
            }

            echo "商品 {$item['id']} 入库完成 $i";
            echo "\r\n";
            $i++;
        }

        echo "脚本处理完毕";
    }
}
