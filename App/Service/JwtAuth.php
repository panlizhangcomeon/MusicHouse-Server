<?php

namespace App\Service;

use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Config;
use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;

class JwtAuth
{
    use Singleton;
    /**
     * 加密算法
     * @var string
     */
    private $_singAlgorithm = 'HS256';
    /**
     * 加密解密的key
     * @var string
     */
    private $_key;
    /**
     * jwt有效期
     */
    private $_exp;

    public function __construct()
    {
        $this->_key = Config::getInstance()->getConf('jwt.key');
        $this->_exp = Config::getInstance()->getConf('jwt.expire');
    }

    /**
     * 生成jwt
     * @param array $data 存储数据
     * @param int $expire token有效期， 秒
     * @param null $uuid token标识，此处默认值则自动根据时间戳生成唯一标识
     * @return string
     * @throws Exception
     * @throws \Exception
     */
    public function encode(array $data, $expire = 0, $uuid = null)
    {
        if (is_null($uuid) && !isset($data['jti'])) {
            $data['jti'] = Uuid::uuid1()->toString();
        }
        $current = time();
        $data['iat'] = $current;
        $data['exp'] = $current + ($expire > 0 ? (int)$expire : $this->_exp);
        return JWT::encode($data, $this->_key);
    }

    /**
     * jwt内容解密
     * @param $jwt
     * @return array
     */
    public function decode($jwt)
    {
        return (array)JWT::decode($jwt, $this->_key, [$this->_singAlgorithm]);
    }
}
