<?php

namespace App\Crontab;

use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\Logger;
use App\Model\UserModel;
use EasySwoole\Utility\File;
use EasySwoole\EasySwoole\Config;

class TaskFile extends AbstractCronTask {

    private $userTable = 'user';

    public static function getRule(): string
    {
        // TODO: Implement getRule() method.
        return '*/1 * * * *';
    }

    public static function getTaskName(): string
    {
        // TODO: Implement getTaskName() method.
        return 'taskFile';
    }

    function run(int $taskId, int $workerIndex)
    {
        // TODO: Implement run() method.
        $userModel = new UserModel();
        $data = $userModel->getAll($this->userTable);
        $avatarList = [];
        if (!empty($data)) {
            foreach ($data as $item) {
                $avatarList[] = $item['avatar'];
            }
        }
        $avatarPath = Config::getInstance()->getConf('avatar_path');
        $scanList = File::scanDirectory($avatarPath);
        $existAvatarList = [];
        if (!empty($scanList['files'])) {
            $existAvatarList = $scanList['files'];
        }
        $unUseAvatarList = array_diff($existAvatarList, $avatarList);
        if (!empty($unUseAvatarList)) {
            foreach ($unUseAvatarList as $path) {
                @unlink($path);
            }
        }
    }

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Logger::getInstance()->log('crontab任务异常[' . $throwable->getMessage() . ']', Logger::LOG_LEVEL_ERROR, 'Crontab');
    }
}
