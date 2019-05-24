<?php

class HandleOnce
{
    /**
     * 修复媒体资源前缀
     */
    public function fixMediaPrefix()
    {
        $db = pdo();
        $db->tableName = 'goods';
        $list = $db->findAll('1=1', '*', 'id asc');

        foreach ($list as $info) {
            if (!empty($info['preview'])) {
                $data['preview'] = preg_replace('/https(.*?)\.net/i','', $info['preview']) ;
            }

            if (!empty($info['screenshots'])) {
                $data['screenshots'] = preg_replace('/https(.*?)\.net/i','', $info['screenshots']) ;
            }

            if (!empty($info['cover_image'])) {
                $data['cover_image'] = str_replace('https://store.playstation.com', '', $info['cover_image']);
            }

            if (!empty($data)) {
                $db->update($data, array('id' => $info['id']));
                echo "数据校正成功 {$info['id']} \r\n";
            }
        }
    }

}
