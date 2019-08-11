<?php

class HandleGameFaqs
{
    public function getItemUrl()
    {
        $service = s('Common');
//        $service->is_proxy = true;

        $host = 'https://gamefaqs.gamespot.com';
        $redis = r('psn_redis');
        $item_url_key = 'faqs_ps4_game_url';
        $item_url_quene_key = 'faqs_ps4_game_url_quene';
        $page = 0;
        $header = array(
            ":method: GET",
            ":scheme: https",
            ":path: /ps4/category/999-all?page={$page}",
            ":authority: gamefaqs.gamespot.com",
            "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36",
            "cookie: gf_dvi=NWQ0ZmIyZGNhZjJiZjViOWQzZjA5YzAwMDA5Y2MxZGIyNTRlZDIwMWQ0NWU2YjFjOGMzZTNiZDQ0ZjUzNWQ0ZmIyZGM%3D; spt=yes; XCLGFbrowser=VIerDV1Pst%2BniwA1Kh0; LDCLGFbrowser=6d28ae80-f857-45af-bed6-ec8332ae3f35; s_vnum=1568096269377%26vn%3D1; s_invisit=true; s_lv_gamefaqs_s=First%20Visit; __utma=132345752.586949792.1565504270.1565504270.1565504270.1; __utmb=132345752.0.10.1565504270; __utmc=132345752; __utmz=132345752.1565504270.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); AMCVS_10D31225525FF5790A490D4D%40AdobeOrg=1; gf_geo=MTc1LjQzLjI0NS4xODU6MTU2OjA%3D; fv20190812=1; dfpsess=m; AMCV_10D31225525FF5790A490D4D%40AdobeOrg=-894706358%7CMCIDTS%7C18120%7CMCMID%7C54525299530706158401897833863038794234%7CMCAAMLH-1566109070%7C11%7CMCAAMB-1566109072%7CRKhpRz8krg2tLO6pguXWp5olkAcUniQYPHaMWWgdJ3xzPWQmdj0y%7CMCOPTOUT-1565511470s%7CNONE%7CvVersion%7C2.3.0%7CMCAID%7C2EA7D971052E7B7F-40002CA500002986; s_cc=true; aam_uuid=54754776598986281841874059461841763458; trc_cookie_storage=taboola%2520global%253Auser-id%3D60233d33-4ece-4641-8644-f4615338d135-tuct3d6854e; s_getNewRepeat=1565504278612-New; s_lv_gamefaqs=1565504278614; QSI_HistorySession=https%3A%2F%2Fgamefaqs.gamespot.com%2Fps4%2Fcategory%2F999-all%3Fpage%3D0~1565504281847",
        );

        $list = $redis->zRange($item_url_key, 0, -1);
        $redis->delete($item_url_quene_key);
        foreach ($list as $item) {
            $redis->lpush($item_url_quene_key, $item);
        }
        exit;
        $i = 1;
        while (true) {
            $url = "https://gamefaqs.gamespot.com/ps4/category/999-all?page={$page}";
            $response = $service->curl($url, $header);
            if ($service->hasError()) {
                echo "curl 发生异常：{$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->is_change_proxy = true;
                continue;
            }

            //去掉标签与标签 标签与内容之间的空格
            $response = preg_replace('/(?<=\>)[\s]+(?=\<)/i', '', $response);
            $response = str_replace('&nbsp;', '', $response);
            preg_match('/<header class=\"page-header\"><h1 class=\"page-title\">(.*?)<\/h1><\/header>/is', $response, $content);
            if ($content[1] == "Blocked IP Address") {
                echo "Blocked IP Address \r\n";
                $i = 1;
                $service->is_change_proxy = true;
                continue;
            }

            $preg = '/<td class=\"rtitle\"><a(.*?)href=\"(.*?)\">/i';
            preg_match_all($preg, $response, $matches);

            if (empty($matches[2])) {
                $total = $redis->zCard($item_url_key);
                echo "所有游戏链接都已处理完成, 共{$total} \r\n";
                break;
            }
            foreach ($matches[2] as $item) {
                $url = $item;
                $redis->zAdd($item_url_key, $i, $url);
                $i++;
            }

            $count = count($matches[2]);
            echo "第{$page}页游戏链接处理完成 处理数据{$count}条 \r\n";
            sleep(3);
            $page++;
        }
    }

    public function getData()
    {
        $service = s('Common');
//        $service->is_proxy = true;

        $db = pdo();
        $redis = r('psn_redis');
        $item_url_quene_key = 'faqs_ps4_game_url_quene';

        $host = 'https://gamefaqs.gamespot.com';
        $header = array(
            ":method: GET",
            ":scheme: https",
            ":path: /ps4/805577-bloodborne/data",
            ":authority: gamefaqs.gamespot.com",
            "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36",
            "cookie: gf_dvi=NWQ0ZmIyZGNhZjJiZjViOWQzZjA5YzAwMDA5Y2MxZGIyNTRlZDIwMWQ0NWU2YjFjOGMzZTNiZDQ0ZjUzNWQ0ZmIyZGM%3D; spt=yes; XCLGFbrowser=VIerDV1Pst%2BniwA1Kh0; LDCLGFbrowser=6d28ae80-f857-45af-bed6-ec8332ae3f35; s_vnum=1568096269377%26vn%3D1; s_invisit=true; s_lv_gamefaqs_s=First%20Visit; __utma=132345752.586949792.1565504270.1565504270.1565504270.1; __utmb=132345752.0.10.1565504270; __utmc=132345752; __utmz=132345752.1565504270.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); AMCVS_10D31225525FF5790A490D4D%40AdobeOrg=1; gf_geo=MTc1LjQzLjI0NS4xODU6MTU2OjA%3D; fv20190812=1; dfpsess=m; AMCV_10D31225525FF5790A490D4D%40AdobeOrg=-894706358%7CMCIDTS%7C18120%7CMCMID%7C54525299530706158401897833863038794234%7CMCAAMLH-1566109070%7C11%7CMCAAMB-1566109072%7CRKhpRz8krg2tLO6pguXWp5olkAcUniQYPHaMWWgdJ3xzPWQmdj0y%7CMCOPTOUT-1565511470s%7CNONE%7CvVersion%7C2.3.0%7CMCAID%7C2EA7D971052E7B7F-40002CA500002986; s_cc=true; aam_uuid=54754776598986281841874059461841763458; trc_cookie_storage=taboola%2520global%253Auser-id%3D60233d33-4ece-4641-8644-f4615338d135-tuct3d6854e; s_getNewRepeat=1565504278612-New; s_lv_gamefaqs=1565504278614; QSI_HistorySession=https%3A%2F%2Fgamefaqs.gamespot.com%2Fps4%2Fcategory%2F999-all%3Fpage%3D0~1565504281847",

        );

        $i = 1;
        while ($redis->lLen($item_url_quene_key) > 0) {
            $url = $redis->rPop($item_url_quene_key);
            $request = $host . $url . '/data';
//            $request = 'https://gamefaqs.gamespot.com/ps4/228476-mega-man-11/data';
            preg_match('/\/ps4\/(.*?)-/is', $request, $match);
            $faq_id = trim($match[1]);
            unset($match);

            if ($i > 100) {
                $i = 1;
                $service->is_change_proxy = true;
            }

            $response = $service->curl($request, $header);
            if ($service->hasError()) {
                echo "curl 发生异常：{$request} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();
                $i = 1;
                $service->is_change_proxy = true;
                $redis->rPush($item_url_quene_key, $url);
                continue;
            }

            //去掉标签与标签 标签与内容之间的空格
            $response = preg_replace('/(?<=\>)[\s]+(?=\<)/i', '', $response);
            $response = str_replace('&nbsp;', '', $response);
            preg_match('/<header class=\"page-header\"><h1 class=\"page-title\">(.*?)<\/h1><\/header>/is', $response, $content);
            if ($content[1] == "Blocked IP Address") {
                echo "Blocked IP Address \r\n";
                $i = 1;
                $service->is_change_proxy = true;
                $redis->rPush($item_url_quene_key, $url);
                continue;
            }

            if ($content[1] == "404 Error: Page Not Found") {
                echo "404 Error: Page Not Found \r\n";
                continue;
            }

            preg_match('/<header class=\"page-header\">(.*?)<\/header>/is', $response, $content);
            $html = $content[0];

            //获取游戏名称
            $preg = '/<h1 class=\"page-title\">(.*?)&ndash; Release Details<\/h1>/is';
            preg_match($preg, $html, $match);
            $origin_name = trim($match[1]);

            if (empty($origin_name)) {
                echo "gamefaqs：{$request} 资料名称获取失败 {$i} \r\n";
                $i = 1;
                $service->is_change_proxy = true;
                var_dump($response);
                $redis->lPush($item_url_quene_key, $url);
                continue;
            }
            //获取支持的平台
            $preg = '/<span class=\"header_more\"(.*?)>(.*?)<\/span>/is';
            preg_match($preg, $html, $match);
            if (!empty($match[1])) {
                $platform = trim(str_replace('<i class="fa fa-caret-down"></i>', '',$match[2]));
                unset($match);
                preg_match_all('/<span class=\"also_name\">(.*?)<\/span>/is', $html, $match);
                if (!empty($match[1])) {
                    $match_str = implode(',', $match[1]);
                    $platform .= ',' . $match_str;
                }
            } else {
                $platform = trim($match[2]);
            }
            unset($match);

            //去掉标签与标签 标签与内容之间的空格
            $response = preg_replace('/(?<=\>)[\s]+(?=\<)/i', '', $response);

            preg_match('/<div class=\"pod pod_gameinfo\">(.*?)<div class=\"pod\">/is', $response, $content);
            $html = $content[0];

            //获取开发商
            $preg = '/<li><b>Developer:(.*?)<a href=\"(.*?)\">(.*?)<\/a><\/li>/is';
            preg_match($preg, $html, $match);
            $developer = trim($match[3]);
            unset($match);

            //获取发行商
            $preg = '/<li><b>Publisher:(.*?)<a href=\"(.*?)\">(.*?)<\/a><\/li>/is';
            preg_match($preg, $html, $match);
            $publisher = trim($match[3]);
            unset($match);

            if (empty($developer) && empty($publisher)) {
                $preg = '/<li><b>Developer\/Publisher:(.*?)<a href=\"(.*?)\">(.*?)<\/a><\/li>/is';
                preg_match($preg, $html, $match);
                $developer = trim($match[3]);
                $publisher = trim($match[3]);
                unset($match);
            }

            //获取发行时间
            $preg = '/<li><b>Release:(.*?)<a href=\"(.*?)\">(.*?)<\/a><\/li>/is';
            preg_match($preg, $html, $match);
            $release = trim($match[3]);
            unset($match);
            //获取系列
            $preg = '/<li><b>Franchise:(.*?)<a href=\"(.*?)\">(.*?)<\/a><\/li>/is';
            preg_match($preg, $html, $match);
            $franchise = trim($match[3]);
            unset($match);

            //获取mc评分
            $preg = '/<div class=\"score metacritic_high\">(.*?)<\/div>/is';
            preg_match($preg, $html, $match);
            $mc_score = trim($match[1]);
            unset($match);

            //获取游戏扩展关联(DLC)
            $preg = '/<li><b>Expansion for:(.*?)<a href=\"(.*?)\">(.*?)<\/a><\/li>/is';
            preg_match($preg, $html, $match);
            $expansion_url = trim($match[2]);
            unset($match);
            preg_match('/\/ps4\/(.*?)-/is', $expansion_url, $match);
            $parent_faq_id = trim($match[1]);
            unset($match);


            preg_match('/<div class=\"pod pod_titledata\">(.*?)<div class=\"pod\">/is', $response, $content);
            $html = $content[0];

            //获取本地游戏人数
            $preg = '/<dt>Local Players:<\/dt><dd>(.*?)<\/dd>/is';
            preg_match($preg, $html, $match);
            $local_player = trim($match[1]);
            unset($match);

            //获取线上游戏人数
            $preg = '/<dt>Online Players:<\/dt><dd>(.*?)<\/dd>/is';
            preg_match($preg, $html, $match);
            $online_player = trim($match[1]);
            unset($match);

            preg_match('/<div class=\"body\"><table class=\"contrib\">(.*?)<\/table>/is', $response, $content);
            $html = $content[0];

            preg_match_all('/<td class=\"cregion\">US<\/td><td class=\"datacompany\"><a href=\"([^<>]+)\">([^<>]+)<\/a><\/td><td class=\"datapid\">([^<>]+)<\/td><td class=\"datapid\">(PlayStation Store PS4|DLC)/is', $html, $match);
            //            var_dump($match[2], $match[4]);
            $product_id = trim($match[3][0]);

            $np_title_id = '';
            if (!empty($product_id) && strpos($product_id, '-') !== false) {
                $np_title_id = str_replace('-','', $product_id);
                $np_title_id = $np_title_id . '_00';
            }
            unset($match);

            //获取扩展数据
            $request = $host . $url;
//            $request = 'https://gamefaqs.gamespot.com/ps4/179029-alekhines-gun';
            $response = $service->curl($request, $header);
            if ($service->hasError()) {
                echo "curl 发生异常：{$request} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();
                $i = 1;
                $service->is_change_proxy = true;
                $redis->rPush($item_url_quene_key, $url);
                continue;
            }

            //去掉标签与标签 标签与内容之间的空格
            $response = preg_replace('/(?<=\>)[\s]+(?=\<)/i', '', $response);
            $response = str_replace('&nbsp;', '', $response);
            preg_match('/<header class=\"page-header\"><h1 class=\"page-title\">(.*?)<\/h1><\/header>/is', $response, $content);
            if ($content[1] == "Blocked IP Address") {
                echo "Blocked IP Address \r\n";
                $i = 1;
                $service->is_change_proxy = true;
                $redis->rPush($item_url_quene_key, $url);
                continue;
            }

            $preg = '/<h2 class=\"title\">User Ratings<\/h2>(.*?)<input type=\"hidden\" id=\"stat_own\"/is';
            preg_match($preg, $response, $content);
            $html = $content[1];

            $preg = '/<div class=\"rating mygames_stats_rate (.*?)\"><a href=(.*?)>(.*?) \/ 5<\/a><\/div>/is';
            preg_match($preg, $html, $match);
            $rating = trim($match[3]);
            unset($match);

            $preg = '/<div class=\"rating mygames_stats_diff (.*?)\"><a href=(.*?)>(.*?)<\/a><\/div>/is';
            preg_match($preg, $html, $match);
            $difficulty = trim($match[3]);
            unset($match);

            $preg = '/<div class="rating mygames_stats_time (.*?)"><a href=(.*?)>(.*?)<\/a><\/div>/is';
            preg_match($preg, $html, $match);
            $play_time = trim($match[3]);
            unset($match);

            //                        var_dump($origin_name, $platform, $developer, $publisher, $release, $franchise, $expansion_url, $parent_faq_id, $mc_score, $local_player, $online_player, $np_title_id);


            $db->tableName = 'faq_data';
            $where['faq_id'] = $faq_id;
            $result = $db->find($where);

            $data = array(
                'faq_id' => $faq_id ? : 0,
                'faq_url' => $url ? : '',
                'parent_faq_id' => $parent_faq_id ? : 0,
                'product_id' => $product_id ? : '',
                'np_title_id' => $np_title_id ? : '',
                'origin_name' => $origin_name ? : '',
                'platform' => $platform ? : '',
                'developer' => $developer ? : '',
                'publisher' => $publisher ? : '',
                'franchises' => $franchise ? : '',
                'release' => $release ? : '',
                'local_players' => $local_player ? : '',
                'online_players' => $online_player ? : '',
                'mc_score' => $mc_score ? : 0,
                'rating' => $rating ? : '',
                'difficulty' => $difficulty ? : '',
                'play_time' => $play_time ? : '',
            );

            if (empty($result)) {
                $data['create_time'] = time();
                $db->insert($data);
            } else {
                $data['update_time'] = time();
                $db->update($data, $where);
            }
            echo "gamefaqs：{$faq_id} {$origin_name} 资料更新成功 {$i} \r\n";
            unset($response, $data);
            $i++;
            $rand = mt_rand(1,3);
            sleep(3);
        }

        echo "所有资料更新完毕 \r\n";
    }



    public function relationGameId()
    {
        $db = pdo();
        $db->tableName = 'game';
        $list = $db->findAll('1=1', '*', 'id asc');

        if (empty($list)) {
            return false;
        }

        $service = s('Common');
        foreach ($list as $game) {
            $request = 'https://store.playstation.com/valkyrie-api/en/us/19/resolve/' . $game['np_title_id'];
            $response = $service->curl($request);
            if ($service->hasError()) {
                echo "curl 发生异常：{$request} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                $service->flushError();

                $request = 'https://store.playstation.com/valkyrie-api/en/US/19/resolve/' . $game['np_title_id'];
                $response = $service->curl($request);
                if ($service->hasError()) {
                    echo "curl 发生异常：{$request} {$service->getErrorCode()}  {$service->getErrorMsg()} \r\n";
                    $service->flushError();
                    continue;
                }
                if (is_array($response)) {
                    echo "response 数据无法解析 \r\n";
                    var_dump($response);
                    continue;
                }
            }

            $data     = json_decode($response, true);
            if (empty($data['included'])) {
                echo "np_title_id:{$game['np_title_id']} 获取美区数据失败 \r\n";
                var_dump($data);
                continue;
            }

            $item = $data['included'][0];
            $product_id = $item['id'];
            $product_id_arr = explode('-', $product_id);
            $np_title_id    = $product_id_arr[1];

            if (!empty($np_title_id)) {
                $where['np_title_id'] = $np_title_id;
                $where['parent_faq_id'] = 0;
                $db->tableName = 'faq_data';
                $info = $db->find($where, '*', '`release_date` asc');
                if (!empty($info)) {
                    $update['game_id'] = $game['game_id'];
                    $condition['id'] = $info['id'];
                    $condition['np_title_id'] = $np_title_id;
                    $db->update($update, $condition);
                } else {
                    echo "数据库无法找到np_title_id {$game['np_title_id']} {$np_title_id} 的相关资料  \r\n";
                    continue;
                }
            }

            echo "游戏资料id {$game['game_id']} 成功关联 gamefaqs \r\n";
        }

        echo "任务处理完成 \r\n";
    }

    public function once()
    {
        $db = pdo();
        $db->tableName = 'faq_data';
        $list = $db->findAll('1=1', '*', 'id asc');

        foreach ($list as $value) {
//            if (!empty($value['product_id']) && strpos($value['product_id'], '-') !== false) {
//                $np_title_id = str_replace('-','', $value['product_id']);
//                $np_title_id = $np_title_id . '_00';
//                $data['np_title_id'] = $np_title_id;
//            }

            $data['release_date'] = strtotime($value['release']) !== false ? strtotime($value['release']) : 0;
            $db->update($data, array('id' => $value['id']));
        }
    }

    /**
     * faq_data 可利用的数据同步至游戏表
     * @return bool
     */
    public function syncToGame()
    {
        $db = pdo();
        $db->tableName = 'faq_data';
        $list = $db->findAll('game_id <> 0', '*', 'id asc');

        if (empty($list)) {
            return false;
        }

        foreach ($list as $item) {
            $where['game_id'] = $item['game_id'];
            $db->tableName = 'game';
            $info = $db->find($where);
            if (empty($info)) {
                echo "game 表找不到相应的资料 game_id:{$item['game_id']} \r\n";
                continue;
            }

            switch ($item['play_time']) {
                case '1 hour': $play_time = 1; break;
                case '80+ hours': $play_time = 99; break;
                default: $play_time = trim(str_replace('hours', '', $item['play_time']));
            }

            switch ($item['difficulty']) {
                case 'Simple':
                case 'Simple-Easy':
                    $difficulty = 1;
                    break;
                case 'Easy':
                case 'Easy-Just Right':
                    $difficulty = 2;
                    break;
                case 'Just Right':
                case 'Just Right-Tough':
                    $difficulty = 3;
                    break;
                case 'Tough':
                    $difficulty = 4;
                    break;
                case 'Tough-Unforgiving':
                case 'Unforgiving':
                    $difficulty = 5;
                    break;
                default:
                    $difficulty = 0;
                    break;
            }

            switch ($item['local_players']) {
                case '1 Player': $local_players = 1; break;
                case '1-2 Players':
                case '2 Players':
                    $local_players = 2; break;
                case '1-3 Players': $local_players = 3; break;
                case '1-4 Players': $local_players = 4; break;
                case '1-5 Players': $local_players = 5; break;
                case '1-6 Players':
                case '2-6 Players':
                    $local_players = 6; break;
                case '1-8 Players': $local_players = 8; break;
                case '1-9 or more Players':
                case '2-9 or more Players':
                    $local_players = 9; break;
                case 'Online Play Only': $local_players = -1; break;
                default:
                    $local_players = null;
            }

            switch ($item['online_players']) {
                case 'Massively Multiplayer':
                case 'Online Multiplayer':
                case 'Up to more than 64 Players':
                    $online_players = 99; break;
                case 'No Online Multiplayer': $online_players = -1; break;
                case '2 Players': $online_players = 2; break;
                case 'Up to 10 Players': $online_players = 10; break;
                case 'Up to 12 Players': $online_players = 12; break;
                case 'Up to 15 Players': $online_players = 15; break;
                case 'Up to 16 Players': $online_players = 16; break;
                case 'Up to 18 Players': $online_players = 18; break;
                case 'Up to 20 Players': $online_players = 20; break;
                case 'Up to 22 Players': $online_players = 22; break;
                case 'Up to 24 Players': $online_players = 24; break;
                case 'Up to 3 Players': $online_players = 3; break;
                case 'Up to 30 Players': $online_players = 30; break;
                case 'Up to 32 Players': $online_players = 32; break;
                case 'Up to 4 Players': $online_players = 4; break;
                case 'Up to 40 Players': $online_players = 40; break;
                case 'Up to 5 Players': $online_players = 5; break;
                case 'Up to 6 Players': $online_players = 6; break;
                case 'Up to 60 Players': $online_players = 60; break;
                case 'Up to 64 Players': $online_players = 64; break;
                case 'Up to 8 Players': $online_players = 8; break;
                case 'Up to 9 Players': $online_players = 9; break;
                default:
                    $online_players = null;
            }

            $update = array();
            $item['developer'] && $update['developer'] = $item['developer'];
            $item['publisher'] && $update['publisher'] = $item['publisher'];
            $item['release_date'] && $update['release_date'] = $item['release_date'];
            $item['mc_score'] && $update['mc_score'] = $item['mc_score'];
            $item['franchises'] && $update['franchises'] = $item['franchises'];
            $item['platform'] && $update['is_only'] = $item['platform'] == 'PlayStation 4' ? 1 : 0 ;
            $play_time && $update['play_time'] = $play_time;
            $difficulty && $update['difficulty'] = $difficulty;
            $local_players && $update['local_players'] = $local_players;
            $online_players && $update['online_players'] = $online_players;
            $db->update($update, $where);

            echo "游戏资料id {$item['game_id']} 成功同步 gamefaqs 数据 \r\n";
        }
        echo "任务处理完成 \r\n";
    }
}
