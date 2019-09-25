<?php

class UserService extends BaseService
{
    public function getUserIdByToken($jwt)
    {
        if (empty($jwt)) {
            return $this->setError('param_jwt_is_empty');
        }

        $service = s('Common');
        $payload = $service->parseJWT($jwt);
        if ($this->hasError()) {
            return $this->setError($this->getError());
        }

        if (empty($payload['user_id'])) {
            return $this->setError('parse_user_id_empty');
        }

        return $payload['user_id'];
    }

    public function getUserInfoByUserId($user_id, $field = array())
    {
        $db = pdo();
        $db->tableName = 'user';
        if (!empty($field) && !in_array('user_id', $field)) {
            array_push($field, 'user_id');
        }
        $field = $field ? implode(',', $field) : '*';
        $result = array();
        if (is_array($user_id)) {
            $user_id = array_unique($user_id);
            $user_id_str = implode("','", $user_id);
            $where = "user_id in ('{$user_id_str}')";
            $list = $db->findAll($where, $field);
            foreach ($list as $user) {
                $result[$user['user_id']] = $user;
            }
        } else {
            $where = "user_id = '{$user_id}'";
            $result = $db->find($where, $field);
        }

        return $result;
    }
}
