<?php
class TrophyTipsService extends BaseService
{
    public function getTrophyTips($np_communication_id, $trophy_id, $page = 1)
    {
        if (empty($np_communication_id)) {
            return $this->setError('param_np_communication_id_is_empty');
        }

        if (!is_numeric($trophy_id)) {
            return $this->setError('param_trophy_id_is_empty');
        }

        $exist = $this->existTrophyTipsFromDb($np_communication_id, $trophy_id);
        if ($exist === true) {
            $list = $this->getTrophyTipsFromDb($np_communication_id, $trophy_id, $page);
        } else {
            $this->getPsnineTips($np_communication_id, $trophy_id);
            $list = $this->getTrophyTipsFromDb($np_communication_id, $trophy_id, $page);
        }

        $result['tips_list'] = $list;
        return $result;
    }

    protected function existTrophyTipsFromDb($np_communication_id, $trophy_id)
    {
        $db = pdo();
        $db->tableName = 'trophy_tips';
        $where['np_communication_id'] = $np_communication_id;
        $where['trophy_id'] = $trophy_id;
        $info = $db->find($where);

        $exist = $info ? true : false;
        return $exist;
    }

    protected function getTrophyTipsFromDb($np_communication_id, $trophy_id, $page = 1, $limit = 20)
    {
        if (empty($np_communication_id)) {
            return $this->setError('param_np_communication_id_is_empty');
        }

        if (!is_numeric($trophy_id)) {
            return $this->setError('param_trophy_id_is_empty');
        }

        $db = pdo();
        $db->tableName = 'trophy_tips';
        $where['np_communication_id'] = $np_communication_id;
        $where['trophy_id'] = $trophy_id;
        $start = ($page - 1 ) * $limit;
        $limit_str = "{$start}, {$limit}";
        $list = $db->findAll($where, 'nickname,avatar,content', 'create_time desc,id asc', $limit_str);
        return $list;
    }

    public function getPsnineTips($np_communication_id, $trophy_id)
    {
        if (empty($np_communication_id)) {
            return $this->setError('param_np_communication_id_is_empty');
        }

        if (!is_numeric($trophy_id)) {
            return $this->setError('param_trophy_id_is_empty');
        }

        $start = (int)substr($np_communication_id, 4, 5);
        $end = str_pad($trophy_id + 1, 3, 0, STR_PAD_LEFT);
        $id = (string)$start . (string)$end;

        $url = "http://psnine.com/trophy/{$id}";
        $cookie = '__Psnine_shell=24b457e81999fb10bc4f0e40c7eb8929; __Psnine_psnid=ifunwee';
        $service = s('Common');
        $response = $service->curl($url, array(), '', 'get', $cookie);
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }

        //替换a标签跳转为#
        $response =  preg_replace_callback('/<a href=\"(.*?)\"/i', function($matchs){
            return str_replace($matchs[1], '#', $matchs[0]);
        }, $response);

        //过滤@xxxxxx
        $preg = '/<a href=\"#\">@(.*?)<\/a>&nbsp;/i';
        $response = preg_replace($preg, '', $response);

        //过滤p9的emoji表情
        /**
        $preg = '/<img src=\"http:\/\/photo.psnine.com\/face\/(.*?)\">/i';
        $response = preg_replace($preg, '', $response);
         */

        //匹配头像
        $preg = '/<a class=\"l\"(.*?)<img src=\"(.*?)\" width/i';
        preg_match_all($preg, $response, $matches);
        $avatar_arr = $matches[2];
        unset($matches);
        //匹配内容
        $preg = '/<div class=\"content pb10\">([\s\S]*?)<\/div>/i';
        preg_match_all($preg, $response, $matches);
        $content_arr = $matches[1];
        unset($matches);
        //匹配昵称
        $preg = '/<a href=\"#\" class=\"psnnode\">(.*?)<\/a>/i';
        preg_match_all($preg, $response, $matches);
        $nickname_arr = $matches[1];
        //匹配节点id
        $preg = '/updown\(this,\'comment\',\'(.*?)\',\'up\'\)/i';
        preg_match_all($preg, $response, $matches);
        $code_arr = $matches[1];

        $list = array();
        $db = pdo();
        $db->tableName = 'trophy_tips';
        if (empty($content_arr)) {
            return $list;
        }

        foreach ($code_arr as $key => $code) {
            $content = $content_arr[$key];
            $content = strip_tags($content, '<br>');
            $content = preg_replace('/<br\\s*?\/??>/i', chr(13) . chr(10), $content);
            $data = array(
                'code'    => trim($code),
                'nickname'  => trim($nickname_arr[$key]),
                'avatar'    => trim($avatar_arr[$key]),
                'content'   => trim($content),
            );

            $data['np_communication_id'] = $np_communication_id;
            $data['trophy_id'] = $trophy_id;
            $data['source'] = 1;
            $data['create_time'] = time();

            $db->preInsert($data);
        }

        $db->preInsertPost();

        $info['tips_num'] = count($code_arr);
        $service = s('TrophyDetail');
        $service->setTrophyInfoToCache($np_communication_id, $trophy_id, $info);
    }

    public function getTrophyTipsNumByNpCommId($np_communication_id)
    {
        $db = pdo();
        $db->tableName = 'trophy_tips';
        $sql = "select trophy_id,count(trophy_id) as tips_num from trophy_tips where np_communication_id = '{$np_communication_id}' group by trophy_id";
        $list = $db->query($sql);
        $data = null;
        foreach ($list as $item) {
            $data[$item['trophy_id']] = $item['tips_num'];
        }

        return $data;
    }

    public function signInPsnine()
    {
        $url = "http://d7vg.com/sign/signin/ajax";
        $post_data = array(
            'psnid' => 'ifunwee',
            'pass' => 'hw2924920',
        );

        $post_data = http_build_query($post_data);
        $header = array(
            "Origin: http://d7vg.com",
        );

        $service = s('Common');
        $response = $service->curl($url, $header, $post_data, 'post');
        if ($service->hasError()) {
            return $this->setError($service->getError());
        }
    }
}
