<?php
class MarketService extends BaseService
{
    /**
     * 获取贷超商品
     */
    public function getGoodsList($page)
    {
        $db = pdo('loan_db');
        $db->tableName = 'goods';
        $list = $db->findAll('status = 1', '*', 'sort desc');

        if (empty($list)) {
            return array();
        }

        foreach ($list as &$item) {
            if ($item['amount_min'] >= 10000) {
                $item['amount_min'] = round($item['amount_min']/10000, 2) . '万';
            }

            if ($item['amount_max'] >= 10000) {
                $item['amount_max'] = round($item['amount_max']/10000, 2) . '万';
            }
        }
        return $list;
    }

}