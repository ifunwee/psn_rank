<?php
class BannerService extends BaseService
{
    public function getList()
    {
        $data = array(
            array(
                'type' => '1',
                'image' => 'http://playstation-image.funwee.com/FYRV8U.jpg',
                'extra' => array(
                    'title' => "合作伙伴介绍",
                    'content' => 'UPGAME 是 Playstation 官方优选店,经营多平台主机游戏/周边/配件。关注公众号「UPGAME玩家快讯」立即体验。（复制后用微信搜索公众号）',
                    'cancel_text' => '取消',
                    'confirm_text' => '复制',
                    'cancel_url' => '',
                    'confirm_url' => '',
                    'clipboard_data' => 'upgame100',
                ),
            ),
        );
        /**
        $data = array(
            array(
                'type' => '1',
                'image' => 'https://homer.dl.playstation.net/pr/bam-art/772/597/c9c75550-d4b8-4bf4-b13a-ac9216f87681.jpg',
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
                'extra' => array(
                    'url' => '../myfollow/myfollow',
                ),
            ),
            array(
                'type' => '3',
                'image' => 'https://homer.dl.playstation.net/pr/bam-art/772/597/c9c75550-d4b8-4bf4-b13a-ac9216f87681.jpg',
                'extra' => array(
                    'appid' => 'wxf7d3846c7c2fc194',
                ),
            ),
            array(
                'type' => '4',
                'image' => 'https://homer.dl.playstation.net/pr/bam-art/775/500/ec0370a1-da5e-45a0-83be-7cb9a2bd6b34.jpg',
                'extra' => array(
                    'url' => 'http://www.baidu.com',
                ),
            ),
        );
        **/

        return $data;
    }
}
