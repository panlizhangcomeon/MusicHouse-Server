<?php

namespace App\Service;

use EasySwoole\Component\Singleton;
use EasySwoole\Log\Logger;

class Log
{
    use Singleton;

    public function log($exception, $msg, string $category = 'debug', int $logLevel = Logger::LOG_LEVEL_ERROR)
    {
        \EasySwoole\EasySwoole\Logger::getInstance()->log($msg . '[' . $exception->getMessage() . ']', $logLevel, $category);
    }
}
