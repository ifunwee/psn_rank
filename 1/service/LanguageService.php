<?php

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2017/5/23
 * Time: 下午2:49
 */
class LanguageService extends BaseLanguageService
{
    protected $cn = array(

        //以下为举例，建议大家以业务或者文件来分段，英文起名尽量以描述清楚为主，因为主要用于我们自身排查bug。
        'system_error'      => '系统出错，请骚后再试～(21000)',
        'param_not_true'    => '发生点小意外，请重试一下～(21001)',
        'auth_failed'       => '发生点小意外，请重试一下～(21002)',
        'auth_key_used'     => '发生点小意外，请重试一下～(21003)',
        'auth_timeout'      => '发生点小意外，请重试一下～(21004)',
        'may_be_attack' => '您的输入包含非法字符，请修改后重试，谢谢～(21005)',
        'param_is_invalid' => '参数不合法～(21006)',

        'in_global_black_list'     => '您已被禁言，无法发送消息。～(21007)',
        'send_message_time_limit'     => '发言速度过快，请稍后再试 ～(21008)',
        'account_aleady_in_room_black' => '该用户已在房间黑名单 ～(21009)',
        'account_already_in_global_black' => '该用户已在全局黑名单 ～(21010)',
        'getRoomBlackList_redis_process_fail'     => '获取房间黑名单缓存失败 ～(21011)',
        'addGlobalBlackList_redis_process_fail'     => '增加全局黑名单缓存失败 ～(21012)',
        'addRoomBlackList_redis_process_fail'     => '增加房间黑名单缓存失败 ～(21013)',
        'addGlobalBlackList_param_is_invalid' => '非法参数～(21014)',
        'roomInstantMessage_param_is_invalid' => '非法参数～(21015)',
        'isAllowBroadcast_param_is_invalid' => '非法参数～(21016)',
        'addGlobalBlackList_token_redis_process_fail' => '保存禁言token失败 ～(21017)',
        'addRoomBlackList_param_is_invalid' => '非法参数 ～(21018)',
        'removeRoomBlackList_param_is_invalid' => '非法参数 ～(21019)',
        'isInGlobalBlackList_param_is_invalid' => '非法参数 ～(21020)',
        'isInRoomBlackList_param_is_invalid' => '非法参数 ～(21021)',

    );
}