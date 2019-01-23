<?php
class FollowService extends BaseService
{
    protected $suffix = '_cn';
    public function __construct($lang = 'cn')
    {
        parent::__construct();
        switch ($lang) {
            case 'en' :
                $this->suffix = '';
                break;
            case 'cn' :
                $this->suffix = '_cn';
                break;
        }
    }

    /**
     * 关注操作 关注|取关
     * @param $open_id
     * @param $goods_id
     * @param $action
     *
     * @return array
     */
    public function operate($open_id, $goods_id, $action)
    {
        if (empty($open_id)) {
            return $this->setError('param_open_id_is_empty', '缺少参数');
        }

        if (empty($goods_id)) {
            return $this->setError('param_goods_id_is_empty', '缺少参数');
        }

        if (empty($goods_id)) {
            return $this->setError('param_action_is_empty', '缺少参数');
        }

        switch ($action) {
            case 'follow' :
                $this->operateFromDb($open_id, $goods_id, 1);
                $this->followToCache($open_id, $goods_id);
                break;
            case 'unfollow':
                $this->operateFromDb($open_id, $goods_id, 0);
                $this->unfollowFromCache($open_id, $goods_id);
                break;
            default:
                return $this->setError('invalid_action');
        }
    }

    /**
     * 操作关注表 （数据库）
     * @param $open_id
     * @param $goods_id
     * @param $status
     *
     * @return
     */
    protected function operateFromDb($open_id, $goods_id, $status)
    {
        $db = pdo();
        $db->tableName = 'follow';
        $where = array(
            'open_id' => $open_id,
            'goods_id' => $goods_id,
        );

        $count = $db->num(array('open_id' => $open_id, 'status' => 1));
        if ((int)$status == 1 && $count > c('follow_limit')) {
            return $this->setError('follow_max_limit', '您的游戏关注数已达上限，请适当删减');
        }

        $info = $db->find($where);
        if (empty($info)) {
            $data = array(
                'open_id' => $open_id,
                'goods_id' => $goods_id,
                'status' => $status,
                'create_time' => time(),
            );

            $db->insert($data);
        } else {
//            if ((int)$status == $info['status']) {
//                return $this->setError('repeat_operate', '请勿重复操作');
//            }
            $data = array(
                'open_id' => $open_id,
                'goods_id' => $goods_id,
                'status' => $status,
                'update_time' => time(),
            );

            $db->update($data, $where);
        }

        return true;
    }

    /**
     * 关注 （缓存）
     * @param $open_id
     * @param $goods_id
     */
    protected function followToCache($open_id, $goods_id)
    {
        $redis = r('psn_redis');
        $account_follow_key = redis_key('account_follow', $open_id);
        $goods_follow_key = redis_key('goods_follow', $goods_id);
        $redis->zAdd($account_follow_key, time(), $goods_id);
        $redis->sAdd($goods_follow_key, $open_id);
    }

    /**
     * 取关 （缓存）
     * @param $open_id
     * @param $goods_id
     */
    protected function unfollowFromCache($open_id, $goods_id)
    {
        $redis = r('psn_redis');
        $account_follow_key = redis_key('account_follow', $open_id);
        $goods_follow_key = redis_key('goods_follow', $goods_id);
        $redis->zRem($account_follow_key, $goods_id);
        $redis->sRem($goods_follow_key, $open_id);
    }

    /**
     * 获取我的关注列表
     * @param $open_id
     * @param $page
     * @param $limit
     */
    public function getMyFollowList($open_id, $page = 1, $limit = 20)
    {
        $goods_id_arr = $this->getMyFollowListFromCache($open_id, $page, $limit);
        if (empty($goods_id_arr)) {
            return array();
        }

        $service = s('Goods');
        $goods_list = $service->getGoodsInfo($goods_id_arr);
        $service = s('GoodsPrice');
        $goods_price = $service->getGoodsPrice($goods_id_arr);

        $list = array();
        foreach ($goods_list as $goods) {
            $info = array(
                'goods_id' => $goods['goods_id'] ? : '',
                'name' => $goods['name'.$this->suffix] ? : '',
                'cover_image' => $goods['cover_image'.$this->suffix] ? : '',
                'rating_score' => $goods['rating_score'] ? : '',
                'rating_total' => $goods['rating_total'] ? : '',
                'language_support' => $goods['language_support'.$this->suffix] ? : '',
                'status' => $goods['status'],
                'price' => $goods_price[$goods['goods_id']],
            );
            $info['cover_image'] = s('Common')->handlePsnImage($info['cover_image']);
            $list[] = $info;
        }

        return $list;
    }

    /**
     * 获取我的关注列表 （缓存）
     * @param $open_id
     * @param $page
     * @param $limit
     *
     * @return array
     */
    public function getMyFollowListFromCache($open_id, $page, $limit)
    {
        $start = ($page - 1 ) * $limit;
        $end = $page * $limit - 1;

        $redis = r('psn_redis');
        $account_follow_key = redis_key('account_follow', $open_id);
        $goods_id_arr = $redis->zRevRange($account_follow_key, $start, $end);

        return $goods_id_arr;
    }

    /**
     * 是否关注
     * @param $open_id
     * @param $goods_id
     *
     * @return string
     */
    public function isFollow($open_id, $goods_id)
    {
        $redis = r('psn_redis');
        $account_follow_key = redis_key('account_follow', $open_id);
        $result = $redis->zScore($account_follow_key, $goods_id);
        $is_follow = $result ? '1' : '0';

        return $is_follow;
    }

}