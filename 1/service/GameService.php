<?php
class GameService extends BaseService
{
    public function getGameInfoFromDb($game_id, $field = array())
    {
        $db = pdo();
        $db->tableName = 'game';
        $field = $field ? implode(',', $field) : '*';
        $result = array();
        if (is_array($game_id)) {
            $game_id = array_unique($game_id);
            $game_id_str = implode("','", $game_id);
            $where = "game_id in ('{$game_id_str}')";
            $list = $db->findAll($where, $field);
            foreach ($list as $game) {
                $result[$game['game_id']] = $game;
            }
        } else {
            $where = "game_id = '{$game_id}'";
            $result = $db->find($where, $field);
        }

        return $result;
    }
}
