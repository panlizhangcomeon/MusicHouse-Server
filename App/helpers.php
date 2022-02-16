<?php
/**
 * 二维数组中查找某个值
 */

use EasySwoole\Redis\Redis as RedisClient;

if (!function_exists('deepInArray')) {
    function deepInArray($value, $array)
    {
        foreach ($array as $item) {
            if (!is_array($item)) {
                if ($item == $value) {
                    return true;
                } else {
                    continue;
                }
            } else {
                if (in_array($value, $item)) {
                    return true;
                } elseif (deepInArray($value, $item)) {
                    return true;
                }
            }
        }
    }
}

/**
 * 获取格式化歌曲时长
 */
if (!function_exists('getFormatDuration')) {
    function getFormatDuration($duration)
    {
        $formatMinute = '00';
        $minutes = $duration / 60;
        if ($minutes < 10) {
            $formatMinute = '0' . floor($minutes);
        } elseif ($minutes > 0) {
            $formatMinute = $minutes;
        }
        $formatSecond = '00';
        $seconds = $duration % 60;
        if ($seconds < 10) {
            $formatSecond = '0' . floor($seconds);
        } elseif ($seconds > 0) {
            $formatSecond = $seconds;
        }
        return $formatMinute . ':' . $formatSecond;
    }
}

/**
 * 设置redis key的有效期
 */
if (!function_exists('setExpire')) {
    function setExpire(RedisClient $redisConnection, string $key, int $expireTime = 86400)
    {
        if ($redisConnection->ttl($key) == -1) {
            //首次添加，永不过期，则将redis键值有效期调整为7天，首次登录后7天内有效
            $redisConnection->expire($key, $expireTime);
        }
    }
}
