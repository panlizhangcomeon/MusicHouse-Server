<?php

namespace App\Model;

use App\HttpController\Music;
use App\Service\DisConstant;
use App\Service\KugouWebApi;
use App\Service\PubSub;
use EasySwoole\Component\TableManager;
use EasySwoole\Redis\Redis as RedisClient;
use EasySwoole\RedisPool\Redis;

class RoomModel extends BaseModel {

    private $roomMusicTable = 'room_music';
    private $roomTable = 'room';
    private $roomComment = 'comment';

    /**
     * 添加房间
     * @param $roomTitle
     * @param $roomDesc
     * @param $username
     * @return array
     */
    public function addRoom($roomTitle, $roomDesc, $username) {
        $returnResult = ['status' => 0, 'errorMsg' => '添加成功', 'room_list' => []];
        $time = time();
        $params = [
            'title' => $roomTitle,
            'desc' => $roomDesc,
            'creator' => $username,
            'create_time' => $time,
            'update_time' => $time
        ];
        $res = $this->insert($this->roomTable, $params);
        if (!$res) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '添加失败';
        }
        return $returnResult;
    }

    /**
     * 删除房间
     * @param $roomid
     * @return array
     */
    public function delRoom($roomid) {
        $returnResult = ['status' => 0, 'errorMsg' => '删除成功', 'room_list' => []];
        $res = $this->raw("delete from $this->roomTable where id = ?", [$roomid]);
        if (!$res) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '删除房间失败';
            return $returnResult;
        }
        $resForMusic = $this->raw("delete from $this->roomMusicTable where roomid = ?", [$roomid]);
        if (!$resForMusic) {
            $returnResult['status'] = -2;
            $returnResult['errorMsg'] = '删除房间音乐失败';
            return $returnResult;
        }
        return $returnResult;
    }

    /**
     * 获取房间列表
     * @return array
     */
    public function getRoomList() {
        $returnResult = ['status' => 0, 'errorMsg' => '查询成功', 'room_list' => []];
        $roomList = $this->getAll($this->roomTable);
        $returnResult['room_list'] = $roomList;
        return $returnResult;
    }

    /**
     * 加入房间
     * @param int $roomId
     * @param string $username
     * @param int $fd
     * @return mixed|null
     */
    public function joinRoom(int $roomId, string $username, int $fd) {
        $returnResult = ['status' => 0, 'errorMsg' => '加入成功', 'roomid' => $roomId];
        $res = Redis::invoke('music', function (RedisClient $client) use ($roomId, $username) {
            $key = 'ROOMUSER:' . $roomId;
            $joinTime = time();
            $joinTimeOld = $client->zScore($key, $username);
            if (!empty($joinTimeOld)) {
                //已存在房间则直接加入
                return true;
            }
            setExpire($client, $key);
            return $client->zAdd($key, $joinTime, $username);
        });
        if (!$res) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '加入房间失败';
        } else {
            //通知房间所有在线用户
            PubSub::getInstance()->publish('room', [
                'action' => 'otherJoinRoom',
                'roomid' => $roomId,
                'username' => $username
            ]);
        }
        return $returnResult;
    }

    /**
     * 退出房间
     * @param int $roomId
     * @param string $username
     * @param int $fd
     * @return array
     */
    public function quitRoom(int $roomId, string $username, int $fd = 0) {
        $returnResult = ['status' => 0, 'errorMsg' => '退出房间成功', 'roomid' => $roomId];
        $res = Redis::invoke('music', function (RedisClient $client) use ($roomId, $username) {
            $key = 'ROOMUSER:' . $roomId;
            return $client->zRem($key, $username);
        });
        if (!$res) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '退出房间失败';
        } else {
            PubSub::getInstance()->publish('room', [
                'action' => 'otherQuitRoom',
                'roomid' => $roomId,
                'username' => $username
            ]);
        }
        return $returnResult;
    }

    /**
     * 获取房间下的用户列表
     * @param $roomId
     * @return array
     */
    public function getRoomUserList($roomId) {
        $result = ['status' => 0, 'errorMsg' => '', 'userlist' => []];
        $data = [];
        $userList = Redis::invoke('music', function (RedisClient $client) use ($roomId) {
            $key = 'ROOMUSER:' . $roomId;;
            return $this->scanSortedSet($client, $key);
        });
        $userModel = new UserModel();
        if (!empty($userList)) {
            asort($userList);
            foreach ($userList as $username => $joinTime) {
                $data[] = [
                    'username' => $username,
                    'avatar' => $userModel->getAvatarByUsername($username),
                    'joinTime' => date('Y-m-d H:i:s', $joinTime)
                ];
            }
            $result['userlist'] = $data;
        }
        return $result;
    }

    private function scanSortedSet(RedisClient $client, $key) {
        $members = [];
        $iterator = null;
        do {
            $scanMembers = $client->zScan($key, $iterator);
            $members += $scanMembers;
        } while ($iterator);
        return $members;
    }

    /**
     * 获取房间下的音乐列表
     * @param $roomId
     * @return array
     */
    public function getRoomMusicList($roomId) {
        $result = ['status' => 0, 'errorMsg' => '', 'data' => []];
        $data = $this->raw("select * from $this->roomMusicTable where roomid = ? order by id desc", [$roomId]);
        if (!empty($data)) {
            $result['data'] = $data;
        }
        return $result;
    }

    /**
     * 获得下一首
     * @param $roomId
     * @param $playingHash
     * @param $type
     * @return array
     */
    public function getNextMusic($roomId, $playingHash, $type) {
        $result = ['status' => 0, 'errorMsg' => '', 'src' => ''];
        $data = $this->raw("select hash,album_id from $this->roomMusicTable where roomid = ? order by id desc", [$roomId]);
        if (!empty($data)) {
            $musicHashArr = $data;
            //$musicHashArr = array_column($data, 'hash');
            $flag = 0;
            foreach ($musicHashArr as $k => $item) {
                if ($item['hash'] == $playingHash) {
                    if ($type == DisConstant::PLAY_RAND) {
                        //随机播放
                        unset($musicHashArr[$k]);
                    } else if ($type === DisConstant::PLAY_LOOP) {
                        //列表循环播放
                        if ($k == count($musicHashArr) - 1) {
                            $flag = 0;
                        } else {
                            $flag = $k + 1;
                        }
                    } else {
                        //单曲循环
                        $flag = $k;
                    }
                }
            }
            $hashKey = $type == DisConstant::PLAY_RAND ? array_rand($musicHashArr) : $flag;
            $hashValue = $musicHashArr[$hashKey]['hash'];
            $albumId = $musicHashArr[$hashKey]['album_id'];
            $result['hash'] = $hashValue;
            $result['album_id'] = $albumId;

            //通知房间所有在线用户切换歌曲
            PubSub::getInstance()->publish('room', [
                'action' => 'getNextMusic',
                'roomid' => $roomId,
                'status' => 0,
                'hash' => $hashValue,
                'album_id' => $albumId
            ]);
        }
        return $result;
    }

    /**
     * 播放音乐
     * @param int $roomId
     * @param string $action
     * @return array
     */
    public function changePlayType(int $roomId, string $action)
    {
        $result = ['status' => 0, 'errorMsg' => ''];
        PubSub::getInstance()->publish('room', [
            'action' => $action,
            'roomid' => $roomId,
            'status' => 0,
        ]);
        return $result;
    }

    /**
     * 获得上一首
     * @param $roomId
     * @param $playingHash
     * @param $type
     * @return array
     */
    public function getPrevMusic($roomId, $playingHash, $type) {
        $result = ['status' => 0, 'errorMsg' => '', 'src' => ''];
        $data = $this->raw("select hash,album_id from $this->roomMusicTable where roomid = ? order by id desc", [$roomId]);
        if (!empty($data)) {
            //$musicHashArr = array_column($data, 'hash');
            $musicHashArr = $data;
            $flag = 0;
            foreach ($musicHashArr as $k => $item) {
                if ($item['hash'] == $playingHash) {
                    if ($type == DisConstant::PLAY_RAND) {
                        //随机播放
                        unset($musicHashArr[$k]);
                    } else if ($type == DisConstant::PLAY_LOOP) {
                        //列表循环播放
                        if ($k == 0) {
                            $flag = count($musicHashArr) - 1;
                        } else {
                            $flag = $k - 1;
                        }
                    } else {
                        //单曲循环
                        $flag = $k;
                    }
                }
            }
            $hashKey = $type == DisConstant::PLAY_RAND ? array_rand($musicHashArr) : $flag;
            $hashValue = $musicHashArr[$hashKey]['hash'];
            $albumId = $musicHashArr[$hashKey]['album_id'];
            $result['hash'] = $hashValue;
            $result['album_id'] = $albumId;

            //通知房间所有在线用户切换歌曲
            PubSub::getInstance()->publish('room', [
                'action' => 'getPrevMusic',
                'roomid' => $roomId,
                'status' => 0,
                'hash' => $hashValue,
                'album_id' => $albumId
            ]);
        }
        return $result;
    }

    /**
     * 添加播放列表
     * @param $roomId
     * @param $songName
     * @param $singerName
     * @param $album
     * @param $hash
     * @param $username
     * @param int $albumId
     * @return array
     */
    public function addRoomMusicList($roomId, $songName, $singerName, $album, $hash, $username, int $albumId) {
        $returnResult = ['status' => 0, 'errorMsg' => '插入成功'];
        $time = time();
        $params = [
            'roomid' => $roomId,
            'songName' => $songName,
            'singerName' => $singerName,
            'album' => $album,
            'hash' => $hash,
            'album_id' => $albumId,
            'create_time' => $time,
            'update_time' => $time
        ];
        $res = $this->insert($this->roomMusicTable, $params);
        if (!$res) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '插入失败';
        } else {
            PubSub::getInstance()->publish('room', [
                'action' => 'otherAddMusic',
                'roomid' => $roomId,
                'songname' => $songName,
                'username' => $username
            ]);
        }
        return $returnResult;
    }

    /**
     * 从播放列表移除
     * @param $roomId
     * @param $hash
     * @param $username
     * @param $songname
     * @return array
     */
    public function deleteRoomMusicList($roomId, $hash, $username, $songname) {
        $returnResult = ['status' => 0, 'errorMsg' => '删除成功'];
        $result = $this->raw("delete from $this->roomMusicTable where roomid = ? and hash = ?", [$roomId, $hash]);
        if (!$result) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '删除失败';
        } else {
            PubSub::getInstance()->publish('room', [
                'action' => 'otherDelMusic',
                'roomid' => $roomId,
                'username' => $username,
                'songname' => $songname
            ]);
        }
        return $returnResult;
    }

    /**
     * 获取房间下的评论
     * @param $roomId
     * @return array
     */
    public function getRoomComment($roomId) {
        $returnResult = ['status' => 0, 'errorMsg' => '查询成功'];
        $userModel = new UserModel();
        $data = $this->raw("select * from $this->roomComment where roomid = ?", [$roomId]);
        if (!empty($data)) {
            foreach ($data as &$value) {
                $value['create_time'] = date('Y-m-d H:i:s', $value['create_time']);
                $value['avatar'] = $userModel->getAvatarByUsername($value['creator']);
            }
            array_multisort(array_column($data, 'create_time'), SORT_DESC, $data); //根据发布时间降序排列
            $returnResult['data'] = $data;
        }
        return $returnResult;
    }

    /**
     * 删除一条评论
     * @param $commentId
     * @param $username
     * @param $roomId
     * @return array
     */
    public function delComment($commentId, $username, $roomId) {
        $returnResult = ['status' => 0, 'errorMsg' => '删除成功'];
        $res = $this->raw("delete from $this->roomComment where id = ?", [$commentId]);
        if (!$res) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '删除失败';
        } else {
            PubSub::getInstance()->publish('room', [
                'action' => 'otherDelComment',
                'username' => $username,
                'roomid' => $roomId
            ]);
        }
        return $returnResult;
    }

    /**
     * 添加评论
     * @param $roomId
     * @param $username
     * @param $comment
     * @return array
     */
    public function addComment($roomId, $username, $comment) {
        $returnResult = ['status' => 0, 'errorMsg' => '添加成功'];
        $time = time();
        $params = [
            'roomid' => $roomId,
            'comment' => $comment,
            'creator' => $username,
            'create_time' => $time,
            'update_time' => $time
        ];
        $res = $this->insert($this->roomComment, $params);
        if (!$res) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '添加失败';
        } else {
            PubSub::getInstance()->publish('room', [
                'action' => 'otherAddComment',
                'roomid' => $roomId,
                'username' => $username,
            ]);
        }
        return $returnResult;
    }

    /**
     * 根据用户名获取fd
     * @param $username
     * @return mixed|null
     */
    public function getFdByUsername($username) {
        return Redis::invoke('music', function (RedisClient $client) use ($username) {
            $fdKey = 'USER:' . $username;
            return $client->get($fdKey);
        });
    }

    /**
     * 获取房间下的用户
     * @param $roomId
     * @return mixed|null
     */
    public function getRoomUser($roomId) {
        $userList = Redis::invoke('music', function (RedisClient $client) use ($roomId) {
            $key = 'ROOMUSER:' . $roomId;;
            return $this->scanSortedSet($client, $key);
        });
        return array_keys($userList);
    }

    /**
     * 选中音乐后通知房间其他人播放
     * @param int $roomId
     * @param string $hash
     * @param int $albumId
     */
    public function chooseMusic(int $roomId, string $hash, int $albumId)
    {
        PubSub::getInstance()->publish('room', [
            'action' => 'getNextMusic',
            'roomid' => $roomId,
            'hash' => $hash,
            'album_id' => $albumId
        ]);
    }
}
