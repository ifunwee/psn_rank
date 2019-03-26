<?php
class ToolsInterface extends BaseInterface
{
    public function upload()
    {
        $service      = s('Tools');
        $business_type = getParam('business_type');
        $result = $service->upload('', $business_type);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }
}