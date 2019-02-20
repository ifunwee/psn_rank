<?php
class HandleTrophy extends BaseService
{
    public function getNPWR()
    {
        $service = s('Profile');
        $info = $service->getApiAccessToken();
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        $is_loop = true;
        $psn_id = 'nmxwzy';
        $offset = 0;
        $limit = 100;
        $url = "https://cn-tpy.np.community.playstation.net/trophy/v1/trophyTitles?";
        $param = array(
            'npLanguage' => 'zh-TW',
            'fields' => '@default,trophyTitleSmallIconUrl',
            'platform' => 'PS4,PSVITA,PS3',
            'returnUrlScheme' => 'http',
            'offset' => $offset,
            'limit' => $limit,
            'comparedUser' => $psn_id,
        );

        $service = s('Common');
        while ($is_loop) {
            $param_str = http_build_query($param);
            $request = $url . $param_str;
            $header = array(
                "Origin: https://id.sonyentertainmentnetwork.com",
                "Authorization:{$info['token_type']} {$info['access_token']}"
            );

            $json = $service->curl($request, $header);
            $json = $service->uncamelizeJson($json);
            $json =  preg_replace_callback('/\"last_update_date\":\"(.*?)\"/', function($matchs){
                return str_replace($matchs[1], strtotime($matchs[1]), $matchs[0]);
            }, $json);

            $data = json_decode($json, true);
            if (empty($data['trophy_titles'])) {
                $is_loop = false;
                echo "处理npwr任务完成";
            } else {
                $now = time();
                $db = pdo();
                foreach ($data['trophy_titles'] as $trophy) {
                    try {
                        $name = addslashes($trophy['trophy_title_name']);
                        $platfrom = $trophy['trophy_title_platfrom'];
                        $sql = "insert into npwr (np_communication_id, trophy_title_name, trophy_title_platfrom, create_time)
                        values('{$trophy['np_communication_id']}', '{$name}', '{$platfrom}', {$now})
                        on duplicate key update update_time = {$now}";

                        $db->exec($sql);
                    } catch (Exception $e) {
                        echo "写入数据库出现异常: {$e->getMessage()} \r\n";
                        continue;
                    }
                    echo "写入数据库成功：{$trophy['np_communication_id']} \r\n";
                }
                $param['offset'] += $limit;
            }
        }
    }
}
