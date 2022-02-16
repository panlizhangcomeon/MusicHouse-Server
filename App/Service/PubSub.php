<?php

namespace App\Service;

use EasySwoole\Component\Singleton;
use EasySwoole\RedisPool\Redis;
use EasySwoole\Redis\Redis as Connection;

class PubSub
{
    use Singleton;

    /**
     * 向频道发布消息
     * @param $channelName
     * @param array $data
     * @return mixed|null
     */
    public function publish($channelName, array $data)
    {
        $data = json_encode($data);
        return Redis::invoke('music', function (Connection $connection) use ($channelName, $data) {
            return $connection->publish($channelName, $data);
        });
    }

    /**
     * 订阅一个频道
     * @param $channelName
     * @param callable $callable
     * @return mixed|null
     */
    public function subscribe($channelName, callable $callable)
    {
        return Redis::invoke('music', function (Connection $connection) use ($channelName, $callable) {
            $connection->subscribe(function (Connection $connection, $channel, $data) use ($callable) {
                $data = json_decode($data, true);
                $callable($channel, $data, $connection);
            }, $channelName);
        });
    }

    /**
     * 取消订阅
     * @param $channelName
     * @return mixed|null
     */
    public function unSubscribe($channelName)
    {
        return Redis::invoke('music', function (Connection $connection) use ($channelName) {
            $connection->unsubscribe($channelName);
        });
    }
}
