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
        $item_url_quene_key = 'ps_price_item_url_quene';
        $page = 1;
        $list = $redis->zRange($item_url_key, 0, -1);
        var_dump($list);exit;
        $redis->delete($item_url_quene_key);
        foreach ($list as $item) {
            $redis->lpush($item_url_quene_key, $item);
        }
        exit;
        while (true) {
            $url = "https://psprices.com/region-hk/games/?platform=PS4&page={$page}";
            $header = array(
            ':method: GET',
            ':authority: psprices.com',
            ':scheme: https',
            ':path: /region-hk/games/?platform=PS4&page=1',
            'cache-control: max-age=0',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.157 Safari/537.36',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
            'referer: https://psprices.com/region-hk/games/?sort=name&platform=PS4',
            'accept-language: zh-CN,zh;q=0.9',
            'cookie: ezovuuidtime_23600=1558865468; ezux_lpl_23600=1558865479070|2efc1c3c-3f88-408a-4c03-f6609362c05b; ezux_ifep_23600=true; ezux_et_23600=69; ezux_tos_23600=114; __cfduid=dc7f70877c8e04cf3f3b6139d9a6ef2341558865396; sixpack_client_id=3fd6a659-277a-4a57-858c-20d9f2695dd3; ezoadgid_23600=-1; ezoref_23600=psprices.com; ezoab_23600=mod34; lp_23600=https://psprices.com/region-hk/games/?platform=PS4; ezovuuid_23600=dc88d5ef-28ce-4de8-707b-258a71bb4196; ezCMPCCS=true; __utma=201383568.415703083.1558865419.1558865419.1558865419.1; __utmc=201383568; __utmz=201383568.1558865419.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); __utmt_e=1; __utmt_f=1; _ym_uid=1558865421840634743; _ym_d=1558865421; _ym_isad=1; _ym_visorc_26749575=w; cf_clearance=84ab604440d2146899ed6f3644af8b4a49a52802-1558863672-3600-250; ezopvc_23600=3; amplitude_id_4c10dcec625be9239a8e354e44c07e43psprices.com=eyJkZXZpY2VJZCI6IjU1YmI4MGM0LTU3NzItNDkyMS1hYzg3LWFjYWZiMzRlYjFiZFIiLCJ1c2VySWQiOm51bGwsIm9wdE91dCI6ZmFsc2UsInNlc3Npb25JZCI6MTU1ODg2NTQxOTYyNiwibGFzdEV2ZW50VGltZSI6MTU1ODg2NTQ3ODMxMywiZXZlbnRJZCI6MCwiaWRlbnRpZnlJZCI6MCwic2VxdWVuY2VOdW1iZXIiOjB9; last_visit=1558836678517::1558865478517; _fathom=%7B%22isNewVisitor%22%3Afalse%2C%22isNewSession%22%3Afalse%2C%22pagesViewed%22%3A%5B%22%2Fregion-hk%2Fgames%2F%3Fplatform%3DPS4%22%2C%22%2Fregion-hk%2Fgames%2F%3Fplatform%3DPS4%26page%3D3%22%2C%22%2Fregion-hk%2Fgames%2F%3Fplatform%3DPS4%26page%3D1%22%5D%2C%22previousPageviewId%22%3A%22tdZNfKLJdj25zWQ71sxl%22%2C%22lastSeen%22%3A1558865478935%7D; __utmb=201383568.16.8.1558865538246',
            'Pragma: no-cache',
            'content-type:charset=UTF-8',
            'Cache-Control: no-cache',
            );

            $response = $service->curl($url, $header);
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
//                var_dump($url);exit;
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
            $header = array(
                ':method: GET',
                ':authority: psprices.com',
                ':scheme: https',
                ':path: /region-hk/game/1772300/the-evil-within-2',
                'cache-control: max-age=0',
                'upgrade-insecure-requests: 1',
                'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.157 Safari/537.36',
                'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
                'referer: https://psprices.com/region-hk/game/1772300/the-evil-within-2',
                'cookie: cf_clearance=3b9c436fb235b7dde953cc7439b4fe0d120dbacb-1558872785-3600-250',
                'Pragma: no-cache',
                'content-type:charset=UTF-8',
                'Cache-Control: no-cache',
            );
//            $url = 'https://psprices.com/region-hk/game/1005013/horizon-zero-dawntm';
            $response = $service->curl($url, $header);
            $response = preg_replace('/(?<=\>)[\s]+(?=\<)/i', '', $response);
            $response = preg_replace('/\r|\n|\r\n|\t|/i', '', $response);

            $preg = '/href=\"https:\/\/store.playstation.com\/en-hk\/product\/(.*?)\"(.*?)Lowest price: <strong>(.*?)<\/strong>,(.*?)PS\+: <strong>(.*?)<\/strong>/is';
            preg_match($preg, $response, $match);
            $goods_id = trim($match[1]);
            $lowest_price = trim($match[3]);
            $plus_lowest_price = trim($match[5]);
//            var_dump($response,$goods_id,$lowest_price,$plus_lowest_price);exit;

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
        /** @var  $service CommonService*/
        $service = s('Common');
//        $service->is_proxy = true;
        $db = pdo();
        $redis = r('psn_redis');
        $item_url_key = 'ps_price_item_url';
        $item_url_quene_key = 'ps_price_item_url_quene';
        $list = $redis->zrange($item_url_key, 0, -1);
        $header = array(
            ':method: GET',
            ':authority: psprices.com',
            ':scheme: https',
            ':path: /region-hk/game/2566697/grip',
            'cache-control: max-age=0',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
            'referer: https://psprices.com/region-hk/games/?platform=PS4',
            'cookie: cf_clearance=ec356f4048544fc44ac64c4ef65590fa8a778cad-1557577405-3600-250',
            'Pragma: no-cache',
            'content-type:charset=UTF-8',
            'Cache-Control: no-cache',
        );
        $i = 1;
        while ($redis->lLen($item_url_quene_key) > 0) {
            $url = $redis->rPop($item_url_quene_key);
            $data = array();
//            $url = 'https://psprices.com/region-hk/game/2450757/divinity-original-sin-2-definitive-edition';
            $response = $service->curl($url, $header);
            if ($service->hasError()) {
                echo "curl 发生异常：{$url} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();
                $service->is_change_proxy = true;
                $redis->rPush($item_url_quene_key, $url);
                continue;
            }
//            https://store.playstation.com/store/api/chihiro/00_09_000/container/HK/en/19/JP0700-CUSA01016_00-ASIA000000000000/image
            $preg = '/data-src=\"https:\/\/store.playstation.com\/store\/api\/chihiro\/00_09_000\/container\/HK\/en\/19\/(.*?)\/image/is';
            preg_match($preg, $response, $match);
            $goods_id = trim($match[1]);
            unset($match);
            if (empty($goods_id)) {
                echo "获取商品id失败 url:{$url} \r\n";
                $service->is_change_proxy = true;
                $redis->lPush($item_url_quene_key, $url);

                //                var_dump($response);
                continue;
            }

            $db->tableName     = 'goods_price';
            $where['goods_id'] = $goods_id;
            $info            = $db->find($where);

            if (empty($info)) {
                echo "商品库找不到该商品: {$goods_id} {$url} \r\n";
                continue;
            }

            $preg = '/"borderColor": "#004acc",(.*?)"data":(.*?), "fill": false, "steppedLine": "before", "label": "Price, HKD"}/is';
            preg_match($preg, $response, $match);
            $item = $match[2];
            $json = '{"history":' . $item . '}';
            $reuslt = json_decode($json, true);
//            var_dump($reuslt);exit;
            foreach ($reuslt['history'] as $key => $value) {
                $data[$key]['goods_id'] = $goods_id;
                $data[$key]['date'] = strtotime($value['x']);
                $data[$key]['price'] = $value['y'] * 100;
            }
            unset($match);


            $preg = '/"borderColor": "#FFC535",(.*?)"data":(.*?), "fill": false, "steppedLine": "before", "label": "PS\+, HKD"}/is';
            preg_match($preg, $response, $match);
            $item = $match[2];
            $json = '{"plus_history":' . $item . '}';
            $reuslt = json_decode($json, true);

            foreach ($reuslt['plus_history'] as $key => $value) {
                $data[$key]['goods_id'] = $goods_id;
                $data[$key]['date'] = strtotime($value['x']);
                $data[$key]['plus_price'] = $value['y'] * 100;
            }
            unset($match);
            //移除当天日期的价格
            array_pop($data);
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
//            $rand = mt_rand(1,3);
//            sleep($rand);
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
