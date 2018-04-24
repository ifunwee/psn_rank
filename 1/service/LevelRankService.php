<?php
class LevelRankService extends BaseService
{
    public function joinRank($group_id, $open_id)
    {
        if (empty($group_id)) {
            return $this->setError('param_group_id_is_empty');
        }

        if (empty($open_id)) {
            return $this->setError('param_open_id_is_empty');
        }

        $redis = r('psn_redis');
        $redis_key = redis_key('account_info', $open_id);
        $psn_id = $redis->hGet($redis_key, 'psn_id');

        $service = s('Profile');
        $info = $service->getUserInfo($psn_id);
        $redis_key = redis_key('level_rank', $group_id);
        $redis->zAdd($redis_key, $info['trophy_summary']['level'], $open_id);

        return true;
    }

    public function getRank($group_id, $open_id)
    {
        $redis = r('psn_redis');
        $redis_key = redis_key('account_info', $open_id);
        $my_info = $redis->hMget($redis_key, array('nick_name', 'gender', 'avatar_url', 'psn_id'));

        //判断是否在排行榜内
        $redis_key = redis_key('level_rank', $group_id);
        $rank = $redis->zRevRank($redis_key, $open_id);
        $my_info['rank'] = $rank === false ? '' : $rank + 1;

        $service = s('Profile');
        $result = $redis->zRevRange($redis_key, 0, -1);
        $list = array();
        foreach ($result as $open_id) {
            $redis_key = redis_key('account_info', $open_id);
            $account_info = $redis->hMget($redis_key, array('nick_name', 'gender', 'avatar_url', 'psn_id'));
            $psn_info = $service->getUserInfo($account_info['psn_id']);
            $temp = array(
                'nick_name' => $account_info['nick_name'],
                'gender' => $account_info['gender'],
                'wx_avatar' => $account_info['avatar_url'],
                'psn_id' => $psn_info['online_id'],
                'psn_avatar' => $psn_info['avatar_url'],
                'level' => $psn_info['trophy_summary']['level'],
                'platinum' => $psn_info['trophy_summary']['earned_trophies']['platinum'],
                'gold' => $psn_info['trophy_summary']['earned_trophies']['gold'],
                'silver' => $psn_info['trophy_summary']['earned_trophies']['silver'],
                'bronze' => $psn_info['trophy_summary']['earned_trophies']['bronze'],
            );

            $list[] = $temp;
            unset($temp);
        }

        $data['my_info'] = $my_info;
        $data['list'] = $list;

        return $data;
    }
}
