<?php

/**
 * PHP实现jwt
 */
class JwtService extends BaseService
{
    private static $key;
    private static $header;

    public function __construct($config)
    {

        self::$key = $config['key'];
        self::$header = array(
            'alg' => $config['alg'] ? : 'HS256',     //生成signature的算法
            'typ' => $config['type'] ? : 'JWT',      //类型
        );
    }

    /**
     * 获取jwt token
     *
     * @param array $payload jwt载荷   格式如下非必须
     * [
     * 'iss'=>'jwt_admin',  //该JWT的签发者
     * 'iat'=>time(),  //签发时间
     * 'exp'=>time()+7200,  //过期时间
     * 'nbf'=>time()+60,  //该时间之前不接收处理该Token
     * 'sub'=>'www.admin.com',  //面向的用户
     * 'jti'=>md5(uniqid('JWT').time())  //该Token唯一标识
     * ]
     *
     * @return bool|string
     */
    public function getToken(array $payload)
    {
        if (is_array($payload)) {
            $base64header  = self::base64UrlEncode(json_encode(self::$header,
                JSON_UNESCAPED_UNICODE));
            $base64payload = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
            $token         = $base64header . '.' . $base64payload . '.' . self::signature($base64header . '.' . $base64payload,
                    self::$key, self::$header['alg']);

            return $token;
        } else {
            return false;
        }
    }


    /**
     * 验证token是否有效,默认验证exp,nbf,iat时间
     *
     * @param string $Token 需要验证的token
     *
     */
    public function verifyToken($token)
    {
        $tokens = explode('.', $token);
        if (count($tokens) != 3) {
            return $this->setError('invalid_jwt');
        }

        list($base64header, $base64payload, $sign) = $tokens;

        //获取jwt算法
        $header = json_decode(self::base64UrlDecode($base64header), JSON_OBJECT_AS_ARRAY);
        if (empty($header['alg'])) {
            return $this->setError('invalid_jwt');
        }

        //签名验证
        if (self::signature($base64header . '.' . $base64payload, self::$key, $header['alg']) !== $sign) {
            return $this->setError('jwt_verification_failed');
        }

        $payload = json_decode(self::base64UrlDecode($base64payload), JSON_OBJECT_AS_ARRAY);

        //签发时间大于当前服务器时间验证失败
        if (isset($payload['iat']) && $payload['iat'] > time()) {
            return $this->setError('jwt_verification_failed');
        }

        //过期时间小于当前服务器时间验证失败
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return $this->setError('jwt_verification_failed');
        }

        //该nbf时间之前不接收处理该Token
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return $this->setError('jwt_verification_failed');
        }

        return $payload;
    }


    /**
     * base64UrlEncode   https://jwt.io/  中base64UrlEncode编码实现
     *
     * @param string $input 需要编码的字符串
     *
     * @return string
     */
    private function base64UrlEncode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * base64UrlEncode  https://jwt.io/  中base64UrlEncode解码实现
     *
     * @param string $input 需要解码的字符串
     *
     * @return bool|string
     */
    private function base64UrlDecode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $addlen = 4 - $remainder;
            $input  .= str_repeat('=', $addlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * HMACSHA256签名   https://jwt.io/  中HMACSHA256签名实现
     *
     * @param string $input 为base64UrlEncode(header).".".base64UrlEncode(payload)
     * @param string $key
     * @param string $alg   算法方式
     *
     * @return mixed
     */
    private function signature($input, $key, $alg = 'HS256')
    {
        $alg_config = array(
            'HS256' => 'sha256'
        );

        return self::base64UrlEncode(hash_hmac($alg_config[$alg], $input, $key, true));
    }
}
