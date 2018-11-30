<?php
class BannerService extends BaseService
{
    public function getList()
    {
        $data = array(
            array(
                'type' => '1',
                'image' => 'https://homer.dl.playstation.net/pr/bam-art/772/597/c9c75550-d4b8-4bf4-b13a-ac9216f87681.jpg',
                'url' => '',
                'extra' => array(
                    'title' => "这里是标题",
                    'content' => '这里是内容',
                    'cancel_text' => '取消',
                    'confirm_text' => '确认',
                    'cancel_url' => '',
                    'confirm_url' => '',
                    'clipboard_data' => '复制到剪切板',
                ),
            ),
            array(
                'type' => '2',
                'image' => 'https://homer.dl.playstation.net/pr/bam-art/775/500/ec0370a1-da5e-45a0-83be-7cb9a2bd6b34.jpg',
                'url' => '../myfollow/myfollow',
                'extra' => array(),
            ),
            array(
                'type' => '3',
                'image' => 'https://homer.dl.playstation.net/pr/bam-art/772/597/c9c75550-d4b8-4bf4-b13a-ac9216f87681.jpg',
                'url' => '',
                'extra' => array(
                    'appid' => 'wxf7d3846c7c2fc194',
                ),
            ),
            array(
                'type' => '4',
                'image' => 'https://homer.dl.playstation.net/pr/bam-art/775/500/ec0370a1-da5e-45a0-83be-7cb9a2bd6b34.jpg',
                'url' => 'http://www.baidu.com',
                'extra' => array(),
            ),
        );

        return $data;
    }
}
