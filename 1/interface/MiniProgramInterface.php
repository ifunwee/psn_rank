<?php
class MiniProgramInterface extends BaseInterface
{
    public function decryptData()
    {
        if (!empty($_POST)) {
            $encrypt_data = getParam('encrypt_data');
            $iv = getParam('iv');
            $code = getParam('code');
            $type = getParam('type');
        } else {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $encrypt_data = $data['encrypt_data'];
            $iv = $data['iv'];
            $code = $data['code'];
            $type = $data['type'];
        }

        $service      = s('MiniProgram');
        $info = $service->decryptData($type, $code, $encrypt_data, $iv);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    public function getAccessToken()
    {
        $service      = s('MiniProgram');
        $info = $service->getAccessToken();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

    public function collectFormId()
    {
        $open_id = getParam('open_id');
        $form_id = getParam('form_id');

        $service      = s('MiniProgram');
        $info = $service->collectFormId($open_id, $form_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($info);
    }

}