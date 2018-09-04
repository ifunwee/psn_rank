<?php
class MiniProgramInterface extends BaseInterface
{
    public function decryptData()
    {
        $encrypt_data = getParam('encrypt_data');
        $iv = getParam('iv');
        $code = getParam('code');
        $type = getParam('type');

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

    public function getTemplate()
    {

    }
}