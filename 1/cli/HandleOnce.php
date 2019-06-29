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
                $data['preview'] = preg_replace('/https:\/\/apollo2.dl.playstation.net/i','', $info['preview']) ;
            }

            if (!empty($info['screenshots'])) {
                $data['screenshots'] = preg_replace('/https:\/\/apollo2.dl.playstation.net/i','', $info['screenshots']) ;
            }

            if (!empty($info['cover_image'])) {
                $data['cover_image'] = str_replace('https://store.playstation.com', '', $info['cover_image']);
            }

            if (!empty($data)) {
                $db->update($data, array('goods_id' => $info['goods_id']));
                echo "数据校正成功 {$info['goods_id']} \r\n";
            }
        }
    }

    public function fixLowestPrice()
    {
        $db = pdo();
        $sql = 'update (select *,min(price) as min_price,min(`plus_price`) min_plus_price from `goods_price_history` group by goods_id) a, goods_price b set b.`lowest_price` = a.min_price, b.`plus_lowest_price` = a.min_plus_price where a.goods_id = b.goods_id';
        $db->exec($sql);
    }

}
