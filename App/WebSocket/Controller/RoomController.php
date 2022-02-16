<?php
namespace App\WebSocket\Controller;

use App\Model\RoomModel;
use App\Model\UserModel;
use App\Service\DisConstant;
use App\Service\Log;

class RoomController extends BaseController {

    private $roomModel;

    private $userModel;


    public function __construct() {
        parent::__construct();
        $this->roomModel = new RoomModel();
        $this->userModel = new UserModel();
    }

    public function onRequest(?string $actionName): bool {
        if (!parent::onRequest($actionName)) {
            return false;
        }
        if ($this->preHandleLogin()) {
            $username = $this->getLoginInfo('username');
            $fd = $this->getClientFd();
            $this->userModel->refreshUserFd($username, $fd);
            return true;
        }
        return false;
    }

    /**
     * 获取房间列表
     */
    public function getRoomList() {
        $username = $this->getLoginInfo('username');
        $result = $this->roomModel->getRoomList($username);
        $result['action'] = 'getRoomList';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 添加房间
     */
    public function addRoom() {
        $roomTitle = $this->getArgument('roomtitle', '');
        $roomDesc = $this->getArgument('roomdesc', '');
        $username = $this->getLoginInfo('username');
        $result = $this->roomModel->addRoom($roomTitle, $roomDesc, $username);
        $result['action'] = 'addRoom';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 刪除房间
     */
    public function delRoom() {
        $roomid = $this->getArgument('roomid', 0);
        $result = $this->roomModel->delRoom($roomid);
        $result['action'] = 'delRoom';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 加入房间
     */
    public function joinRoom() {
        $roomId = $this->getArgument('roomid', 0);
        $username = $this->getLoginInfo('username');
        $fd = $this->getClientFd();
        $result = $this->roomModel->joinRoom($roomId, $username, $fd);
        $result['action'] = 'joinRoom';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 退出房间
     */
    public function quitRoom() {
        $roomId = $this->getArgument('roomid', 0);
        $username = $this->getLoginInfo('username');
        $fd = $this->getClientFd();
        $result = $this->roomModel->quitRoom($roomId, $username, $fd);
        $result['action'] = 'quitRoom';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 获取房间在线用户
     */
    public function getRoomUserList() {
        $roomId = $this->getArgument('roomid', 0);
        $result = $this->roomModel->getRoomUserList($roomId);
        $result['action'] = 'getRoomUserList';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 获取房间下的音乐列表
     */
    public function getRoomMusicList() {
        $roomId = $this->getArgument('roomid', 0);
        $result = $this->roomModel->getRoomMusicList($roomId);
        $result['action'] = 'getRoomMusicList';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 播放/暂停
     */
    public function changePlayType()
    {
        try {
            $roomId = $this->getArgument('roomid', 0);//通知房间所有在线用户切换歌曲
            $action = $this->getArgument('action');
            $result = $this->roomModel->changePlayType($roomId, $action);
            $result['action'] = $action;
            $this->response()->setMessage(json_encode($result));
        } catch (\Exception $exception) {
            Log::getInstance()->log($exception, '更改播放状态失败');
        }
    }

    /**
     * 获取下一首(随机/循环)
     */
    public function getNextMusic() {
        $type = $this->getArgument('type', DisConstant::PLAY_RAND);
        $roomId = $this->getArgument('roomid', 0);
        $playingHash = $this->getArgument('hash', '');
        $result = $this->roomModel->getNextMusic($roomId, $playingHash, $type);
        $result['action'] = 'getNextMusic';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 获取上一首(随机/循环)
     */
    public function getPrevMusic() {
        $type = $this->getArgument('type', DisConstant::PLAY_RAND);
        $roomId = $this->getArgument('roomid', 0);
        $playingHash = $this->getArgument('hash', '');
        $result = $this->roomModel->getPrevMusic($roomId, $playingHash, $type);
        $result['action'] = 'getPrevMusic';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 添加到播放列表
     */
    public function addRoomMusicList() {
        $roomId = $this->getArgument('roomid', 0);
        $username = $this->getLoginInfo('username');
        $songName = $this->getArgument('songname', '');
        $singerName = $this->getArgument('singername', '');
        $album = $this->getArgument('album', '');
        $hash = $this->getArgument('hash', '');
        $albumId = $this->getArgument('album_id', 0);
        $result = $this->roomModel->addRoomMusicList($roomId, $songName, $singerName, $album, $hash, $username, $albumId);
        $result['action'] = 'addRoomMusicList';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 从播放列表删除
     */
    public function deleteRoomMusicList() {
        $roomId = $this->getArgument('roomid', 0);
        $hash = $this->getArgument('hash', '');
        $songname = $this->getArgument('songname', '');
        $username = $this->getLoginInfo('username');
        $result = $this->roomModel->deleteRoomMusicList($roomId, $hash, $username, $songname);
        $result['action'] = 'deleteRoomMusicList';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 获取房间下的评论列表
     */
    public function getRoomComment() {
        $roomId = $this->getArgument('roomid', 0);
        $result = $this->roomModel->getRoomComment($roomId);
        $result['action'] = 'getRoomComment';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 删除评论
     */
    public function delComment() {
        $commentId = $this->getArgument('id', 0);
        $roomId = $this->getArgument('roomid', 0);
        $username = $this->getLoginInfo('username');
        $result = $this->roomModel->delComment($commentId, $username, $roomId);
        $result['action'] = 'delComment';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 添加评论
     */
    public function addComment() {
        $roomId = $this->getArgument('roomid', 0);
        $username = $this->getLoginInfo('username');
        $comment = $this->getArgument('comment', '');
        $result = $this->roomModel->addComment($roomId, $username, $comment);
        $result['action'] = 'addComment';
        $this->response()->setMessage(json_encode($result));
    }

    /**
     * 点击播放音乐
     */
    public function chooseMusic()
    {
        try {
            $roomId = $this->getArgument('roomid', 0);
            $hash = $this->getArgument('hash', '');
            $albumId = $this->getArgument('album_id', 0);
            $result = $this->roomModel->chooseMusic($roomId, $hash, $albumId);
            $result['action'] = 'chooseMusic';
            $this->response()->setMessage(json_encode($result));
        } catch (\Exception $exception) {
            Log::getInstance()->log($exception, '播放音乐失败');
        }
    }
}
