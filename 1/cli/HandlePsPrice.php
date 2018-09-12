<?php
use JonnyW\PhantomJs\Client;

class HandlePsPrice
{
    public function getItemUrl()
    {
        $service = s('Common');
        $host = 'https://psprices.com';
        $redis = r('psn_redis');
        $item_url_key = 'ps_price_item_url';
        $page = 1;
        while (true) {
            $url = "https://psprices.com/region-hk/games/?platform=PS4&page={$page}";
            $response = $service->curl($url);
            //<a href="/region-hk/game/1193368/kill-the-bad-guy" class="content__game_card__cover">
            $preg = '/<a href=\"(.*?)\" class=\"content__game_card__cover\">/i';
            preg_match_all($preg, $response, $matches);
            if (empty($matches[1])) {
                $total = $redis->zCard($item_url_key);
                echo "所有商品链接都已处理完成, 共{$total} \r\n";
                break;
            }
            foreach ($matches[1] as $item) {
                $url = $host . $item;
                $redis->zAdd($item_url_key, time(), $url);
            }

            $count = count($matches[1]);
            echo "第{$page}页商品链接处理完成 处理数据{$count}条 \r\n";
            $page++;
        }
    }

    //<div><strong>最低價格</strong>: HK$325,<strong>PS+</strong>: Free</div>
    //<strong>Lowest price</strong>: Free, <strong>PS+</strong>: Free
    public function getLowPrice()
    {
        $service = s('Common');
        $db = pdo();
        $redis = r('psn_redis');
        $item_url_key = 'ps_price_item_url';
        $list = $redis->zrange($item_url_key, 0, -1);
        $i = 1;
        foreach ($list as $url) {
//            $url = 'https://psprices.com/region-hk/game/1005013/horizon-zero-dawntm';
            $response = $service->curl($url);
//            var_dump($response);exit;
            $preg = '/href=\"https:\/\/store.playstation.com\/en-hk\/product\/(.*?)\"(.*?)<strong>Lowest price<\/strong>: (.*?),(.*?)<strong>PS\+<\/strong>: (.*?)<\/div>/is';
            preg_match($preg, $response, $match);
            $goods_id = trim($match[1]);
            $lowest_price = trim($match[3]);
            $plus_lowest_price = trim($match[5]);

            if (empty($goods_id)) {
                continue;
            }

            if ($lowest_price == 'Free') {
                $lowest_price = 0;
            } else {
                $lowest_price = floatval(str_replace('HK$', '', $lowest_price)) * 100;
            }

            if ($plus_lowest_price == 'Free') {
                $plus_lowest_price = 0;
            } else {
                $plus_lowest_price = floatval(str_replace('HK$', '', $plus_lowest_price)) * 100;
            }

            $db->tableName     = 'goods_price';
            $where['goods_id'] = $goods_id;
            $result            = $db->find($where);

            if (empty($result)) {
                echo "商品库找不到该商品: {$goods_id} {$url} \r\n";
                continue;
            }

            $data = array(
                'lowest_price' => $lowest_price,
                'plus_lowest_price' => $plus_lowest_price,
            );
            $db->update($data, $where);
            echo "商品 {$goods_id} 历史低价更新成功 {$i} \r\n";
            $i++;
        }

        echo "所有商品历史价格更新完毕 \r\n";
    }

    public function history()
    {
        $service = s('Common');
        $db = pdo();
        $redis = r('psn_redis');
        $item_url_key = 'ps_price_item_url';
        $list = $redis->zrange($item_url_key, 0, -1);
        $i = 1;
        foreach ($list as $url) {
            $data = array();
//            $url = 'https://psprices.com/region-hk/game/1835423/horizon-zero-dawntm-complete-edition';
            $response = $service->curl($url);
            $preg = '/href=\"https:\/\/store.playstation.com\/en-hk\/product\/(.*?)\"/is';
            preg_match($preg, $response, $match);
            $goods_id = trim($match[1]);
            if (empty($goods_id)) {
                echo "获取商品id失败 url:{$url} \r\n";
                continue;
            }

            $db->tableName     = 'goods_price';
            $where['goods_id'] = $goods_id;
            $result            = $db->find($where);
//            var_dump($goods_id);exit;

            if (empty($result)) {
                echo "商品库找不到该商品: {$goods_id} {$url} \r\n";
                continue;
            }

            $preg = '/name: \"Price\",(.*?)data: \[(.*?)\[Date\.now/is';
            preg_match($preg, $response, $match);
            $item = str_replace(" - 1", '', $match[2]);
            $item =  preg_replace_callback('/Date\.UTC\((.*?)\)/', function($matchs){
                $date = str_replace(', ', '-', $matchs[1]);
                return $matchs[0] = strtotime($date);
            }, $item);
            $item = substr(trim($item), 0, -1);
            $json = '{"history":['. $item .']}';
            $reuslt = json_decode($json, true);
            foreach ($reuslt['history'] as $key => $value) {
                $data[$key]['goods_id'] = $goods_id;
                $data[$key]['date'] = $value[0];
                $data[$key]['price'] = $value[1] * 100;
            }

            $preg = '/name: \"PS\+\",(.*?)data: \[(.*?)\[Date\.now/is';
            preg_match($preg, $response, $match);
            $item = str_replace(" - 1", '', $match[2]);
            $item =  preg_replace_callback('/Date\.UTC\((.*?)\)/', function($matchs){
                $date = str_replace(', ', '-', $matchs[1]);
                return $matchs[0] = strtotime($date);
            }, $item);
            $item = substr(trim($item), 0, -1);
            $json = '{"plus_history":['. $item .']}';
            $reuslt = json_decode($json, true);

            foreach ($reuslt['plus_history'] as $key => $value) {
                $data[$key]['goods_id'] = $goods_id;
                $data[$key]['date'] = $value[0];
                $data[$key]['plus_price'] = $value[1] * 100;
            }
//var_dump($data);exit;
            try {
                $db->tableName = 'goods_price_history';
                $db->delete(array('goods_id' => $goods_id));
                foreach ($data as $info) {
                    $info['create_time'] = time();
                    $db->preInsert($info);
                }

                $db->preInsertPost();
            } catch (Exception $e) {
                echo "数据库操作异常：{$e->getMessage()} url:{$url} \r\n";
                continue;
            }

            echo "商品 {$goods_id} 历史价格同步完成 {$i} \r\n";
            $i++;
        }

        echo "脚本处理完毕 \r\n";
    }

    public function test()
    {

        $client = Client::getInstance();
        //这一步非常重要，务必跟服务器的phantomjs文件路径一致
        $client->getEngine()->setPath('/usr/local/bin/phantomjs');
        $client->isLazy(); // 让客户端等待所有资源加载完毕

        /**
         * @see JonnyW\PhantomJs\Http\Request
         **/
        $request = $client->getMessageFactory()->createRequest('https://market-h5.taqu.cn/html/live/ranking/PK/season7/index.html?season=10', 'GET');
        $request->setTimeout(50000);
        /**
         * @see JonnyW\PhantomJs\Http\Response
         **/
        $response = $client->getMessageFactory()->createResponse();

        // Send the request
        $client->send($request, $response);
        echo $response->getContent();

//        if($response->getStatus() === 200) {
//
//            // Dump the requested page content
//            echo $response->getContent();
//        }
    }

}
