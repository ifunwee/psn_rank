<?php
class HandlePs4Game extends BaseService
{
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
                'language_support' => is_numeric($index) ? $attr['skus'][$index]['name'] : '',
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
            if ($this->hasError()) {
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
        $default_sku_id = $attr['default-sku-id'];
        $index = null;

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

        $db = pdo();
        $db->tableName     = 'goods_price';
        $where['goods_id'] = $item['id'];
        $result            = $db->find($where);

        $status            = empty($attr['skus'][$index]['prices']) ? 0 : 1;
        $status            = empty($attr['skus'][$index]['is-preorder']) ? $status : 2;
        $origin_price      = $attr['skus'][$index]['prices']['non-plus-user']['strikethrough-price']['value'];
        $sale_price        = $attr['skus'][$index]['prices']['non-plus-user']['actual-price']['value'];
        $discount          = $attr['skus'][$index]['prices']['non-plus-user']['discount-percentage'];
        $plus_origin_price = $attr['skus'][$index]['prices']['plus-user']['strikethrough-price']['value'];
        $plus_sale_price   = $attr['skus'][$index]['prices']['plus-user']['actual-price']['value'];
        $plus_discount     = $attr['skus'][$index]['prices']['plus-user']['discount-percentage'];
        $start_date        = $attr['skus'][$index]['prices']['non-plus-user']['availability']['start-date'];
        $end_date          = $attr['skus'][$index]['prices']['non-plus-user']['availability']['end-date'];

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

    /**
     * 降价通知
     */
    public function reducePriceNotice()
    {
        $date = strtotime(date('Y-m-d', time()));
        $redis = r('psn_redis');
        $db = pdo();
        $sql = "select a.*,b.plus_origin_price,b.plus_tag from goods_price_history a left join goods_price b on a.goods_id = b.goods_id where a.date = {$date} and a.start_date > 0";
        $list = $db->query($sql);
        if (empty($list)) {
            log::n('discount_goods_is_empty');
            echo date('Y-m-d H:i:s') . " 暂无发现降价商品 \r\n";
            return false;
        }
        $service = s('MiniProgram', 'price');
        $goods_service = s('Goods');
        $goods_id_arr = array_column($list, 'goods_id');

        $goods_info_arr = $goods_service->getGoodsInfo($goods_id_arr);
        foreach ($list as $info) {
            $db->tableName = 'follow';
            $condition['goods_id'] = $info['goods_id'];
            $condition['status'] = 1;
            $follow_list = $db->findAll($condition);
            foreach ($follow_list as $follow_info) {
                $redis_key = redis_key('reduce_price_notice_lock', $follow_info['open_id'], $info['goods_id']);
                $lock = $redis->get($redis_key);
                if ($lock) {
                    echo "reduce_price_notice_lock:{$follow_info['open_id']}  {$info['goods_id']} \r\n";
                    continue;
                }

                $content['touser'] = $follow_info['open_id'];
                $content['template_id'] = 'UXUVm5TNEs3KQD9ei7aBI_QkaVSTizW15vVmOeaBvAM';
                $content['page'] = 'pages/detail/detail?goods_id=' . $info['goods_id'];
                $form_id = $service->getFormId($follow_info['open_id']);
                if ($service->hasError()) {
                    log::w("get_form_id_fail: {$follow_info['open_id']} " . json_encode($service->getError()));
                    $service->flushError();
                    continue;
                }
                $content['form_id'] = $form_id['form_id'];
                $start_date = date('Y-m-d', $info['start_date']);
                $end_date = $info['end_date'] ? date('Y-m-d', $info['end_date']) : '未知期限';
                $content['data'] = array(
                    'keyword1' => array(
                        'value' => $goods_info_arr[$info['goods_id']]['name_cn'],
                    ),
                    'keyword2' => array(
                        'value' => "{$start_date} 至 {$end_date}",
                    ),
                    'keyword3' => array(
                        'value' => $info['plus_origin_price']/100 . '港币',
                    ),
                    'keyword4' => array(
                        'value' => $info['plus_price']/100 . '港币',
                    ),
                    'keyword5' => array(
                        'value' => $info['plus_tag'] > 0 ? $info['plus_tag'] == 1 ? '历史新低': '持平史低' : '无',
                    ),
                );
                $json = json_encode($content);
                $service->sendMessage($json);
                if ($service->hasError()) {
                    echo "send_message_fail: {$follow_info['open_id']} {$info['goods_id']} \r\n";
                    log::w("send_message_fail:" . json_encode($service->getError()) . $json);
                    $service->flushError();
                    continue;
                }
                log::i("send_message_success: {$follow_info['open_id']} {$info['goods_id']}");
                echo "send_message_success: {$follow_info['open_id']} {$info['goods_id']} \r\n";

                $expire_time = strtotime(date('Y-m-d 12:00:00',time() + 86400));
                $redis->set($redis_key, time());
                $redis->expireAt($redis_key, $expire_time);
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
            $data['name_cn'] = $info['name'];
            $data['is_final'] = 1;
            $data['update_time'] = time();
            try {
                $db->update($data, $where);
                echo "更新自定义中文名成功：{$info['goods_id']} {$info['name']} \r\n";
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    public function exportHotGame()
    {
        $db = pdo();
        $db->tableName = 'goods';
        $where = "rating_total > 100";
        $list = $db->findAll($where, '*', 'rating_total desc');
        $data = array();
        foreach ($list as $info) {
            $temp['goods_id'] = $info['goods_id'];
            $temp['name'] = $info['name_cn'];
            $data[] = $temp;
        }

        var_dump(json_encode($data));exit;
    }

}
