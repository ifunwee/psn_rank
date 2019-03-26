<?php
class ToolsService extends BaseService
{
    public $max_size = 10000000; //10M
    public $mimes = array();
    public $exts = array('gif', 'jpg', 'jpeg', 'bmp', 'png');
    public $businiess_type = array('comment');

    public function upload($files, $business_type, $bucket = 'app-image')
    {
        if (empty($files)) {
            $files = $_FILES;
        }
        if (empty($files)) {
            return $this->setError('no_upload_file', '上传的文件为空');
        }

        if (empty($business_type) || !in_array($business_type, $this->businiess_type))
        {
            return $this->setError('param_businiss_type_is_invalid', '无法识别的业务类型');
        }

        $service = s('Qiniu');
        $upload = $fail = array();
        foreach ($files as $key => $file) {
            $file['ext'] = pathinfo($file['name'], 4);
            if (!$this->check($file)) {
                $fail[] = "{$file['name']} 上传失败：{$this->getErrorMsg()}";
                $this->flushError();
                continue;
            }

            $file_data = base64_encode(file_get_contents($file['tmp_name']));
            $sava_name = $business_type . '_' .time() . rand(1000, 9999) . '.' . $file['ext'];
            $response = $service->uploadFile($file_data, $sava_name, $bucket);
            if ($service->hasError()) {
                $fail[] = "{$file['name']} 上传失败：{$this->getErrorMsg()}";
                $this->flushError();
                continue;
            }

            $upload[] = c('app_image_domain').$response;
        }

        $result['upload'] = $upload;
        $result['fail'] = $fail;
        return $result;
    }

    /**
     * 检查上传的文件
     *
     * @param array $file 文件信息
     */
    private function check($file)
    {
        /* 无效上传 */
        if (empty($file['name'])) {
            $this->setError('file_name_is_empty', '未知上传错误');
            return false;
        }

        /* 检查是否合法上传 */
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->setError('file_name_is_empty', '非法上传文件');
            return false;
        }

        /* 检查文件大小 */
        if (!$this->checkSize($file['size'])) {
            $this->setError('max_size_limit', '文件大小超过最大限制');
            return false;
        }

        /* 检查文件Mime类型 */
        //TODO:FLASH上传的文件获取到的mime类型都为application/octet-stream
        if (!$this->checkMime($file['type'])) {
            $this->setError('mime_limit', '文件MIME类型不允许');
            return false;
        }

        /* 检查文件后缀 */
        if (!$this->checkExt($file['ext'])) {
            $this->setError('ext_limit', '文件后缀不允许');
            return false;
        }

        /* 通过检测 */

        return true;
    }

    /**
     * 检查文件大小是否合法
     *
     * @param integer $size 数据
     */
    private function checkSize($size)
    {
        return !($size > $this->max_size) || (0 == $this->max_size);
    }

    /**
     * 检查上传的文件MIME类型是否合法
     *
     * @param string $mime 数据
     */
    private function checkMime($mime)
    {
        if (empty($this->mimes)) {
            return true;
        } else {
            return in_array(strtolower($mime), $this->mimes);
        }
    }

    /**
     * 检查上传的文件后缀是否合法
     *
     * @param string $ext 后缀
     */
    private function checkExt($ext)
    {
        if (empty($this->exts)) {
            return true;
        } else {
            return in_array(strtolower($ext), $this->exts);
        }
    }
}
