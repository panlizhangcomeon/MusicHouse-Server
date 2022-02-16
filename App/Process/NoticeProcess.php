<?php

namespace App\Process;

use App\Model\RoomModel;
use App\Service\PubSub;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\ServerManager;

class NoticeProcess extends AbstractProcess
{
    private $roomModel;

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        $this->roomModel = new RoomModel();
    }

    protected function run($arg)
    {
        // TODO: Implement run() method.
        go(function () {
            PubSub::getInstance()->subscribe('room', function ($channelName, $data, $connection) {
                $action = $data['action'] ?? '';
                switch ($action) {
                    case 'otherQuitRoom':
                    case 'otherJoinRoom':
                    case 'otherAddMusic':
                    case 'otherDelMusic':
                    case 'otherAddComment':
                    case 'otherDelComment':
                    case 'getNextMusic':
                    case 'getPrevMusic':
                    case 'pauseMusic':
                    case 'playMusic':
                        $this->notifyClient($data);
                        break;
                    case 'updateToken':
                        $this->notifyFd($data);
                        break;
                }
            });
        });
    }

    /**
     * 通知房间内多个客户端
     * @param $data
     */
    public function notifyClient($data)
    {
        $roomId = $data['roomid'] ?? 0;
        $roomUsers = $this->roomModel->getRoomUser($roomId);
        foreach ($roomUsers as $username) {
            $fd = $this->roomModel->getFdByUsername($username);
            $server = ServerManager::getInstance()->getSwooleServer();
            if ($server->isEstablished($fd)) {
                $server->push($fd, json_encode($data));
            }
        }
    }

    /**
     * 通知单一客户端
     * @param $data
     */
    public function notifyFd($data)
    {
        $fd = $data['fd'] ?? 0;
        $server = ServerManager::getInstance()->getSwooleServer();
        if ($server->isEstablished($fd)) {
            $server->push($fd, json_encode($data));
        }
    }
}
