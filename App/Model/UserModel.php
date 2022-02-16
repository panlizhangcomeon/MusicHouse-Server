<?php

namespace App\Model;

use App\Service\FdManager;
use App\Service\JwtAuth;
use EasySwoole\Component\TableManager;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Redis\Redis as RedisClient;
use EasySwoole\RedisPool\Redis;

class UserModel extends BaseModel
{
    private $userTable = 'user';

    /**
     * 用户登陆
     * @param string $username
     * @param string $password
     * @return array
     * @throws \Exception
     */
    public function login(string $username, string $password): array
    {
        $returnResult = ['status' => 0, 'errorMsg' => ''];
        $result = $this->raw("select * from $this->userTable where username = ? and password = ?", [$username, $password]);
        if (empty($result[0])) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '登陆失败，用户名或密码错误';
        } else {
            $result[0]['logintime'] = time();
            $token = JwtAuth::getInstance()->encode($result[0]);
            $returnResult['token'] = $token;
            $returnResult['userInfo'] = $result;
        }
        return $returnResult;
    }

    /**
     * 注册用户
     * @param string $username
     * @param string $password
     * @param string $area
     * @param string $birthday
     * @param string $likeType
     * @param int $sex
     * @param string $desc
     * @param string $avatar
     * @return array
     */
    public function register(string $username, string $password, string $area, string $birthday, string $likeType, int $sex, string $desc = '', string $avatar = '')
    {
        $returnResult = ['status' => 0, 'errorMsg' => '注册成功'];
        $time = time();
        $params = [
            'username' => $username,
            'password' => $password,
            'area' => $area,
            'birthday' => $birthday,
            'like_type' => $likeType,
            'sex' => $sex,
            'avatar' => $avatar,
            'desc' => $desc,
            'create_time' => $time,
            'update_time' => $time
        ];
        $sql = "insert into $this->userTable(`username`, `password`, `area`, `birthday`, `like_type`, `sex`, `avatar`, `desc`, `create_time`, `update_time`)
                values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $result = $this->raw($sql, $params);
        if (!$result) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '注册失败';
        }
        return $returnResult;
    }

    /**
     * 修改密码
     * @param string $username
     * @param string $oldPassword
     * @param string $newPassword
     * @return array
     * @throws \Exception
     */
    public function changePassword(string $username, string $oldPassword, string $newPassword): array
    {
        $returnResult = ['status' => -1, 'errorMsg' => '修改失败'];
        $oldInfo = $this->raw("select password from $this->userTable where username = ?", [$username]);
        $password = $oldInfo[0]['password'];
        if ($oldPassword != $password) {
            $returnResult['errorMsg'] = '旧密码不正确';
            return $returnResult;
        }
        $result = $this->raw("update $this->userTable set password = ? where username = ?", [$newPassword, $username]);
        if ($result) {
            $returnResult['status'] = 0;
            $returnResult['errorMsg'] = '修改成功';
            $returnResult['token'] = $this->refreshToken($username);
        }
        return $returnResult;
    }

    /**
     * 获取用户信息
     * @param string $token
     * @return array
     */
    public function getUserInfo(string $token): array
    {
        $result = [];
        try {
            $result = JwtAuth::getInstance()->decode($token);
            $result['like_type'] = explode(',', $result['like_type']);
            $result['status'] = 0;
        } catch (\Throwable $throwable) {
            Logger::getInstance()->log('jwt解码失败:' . $throwable->getMessage(), Logger::LOG_LEVEL_ERROR, 'JWT');
            $result['status'] = -1;
            $result['errorMsg'] = $throwable->getMessage();
        }
        return $result;
    }

    /**
     * 根据用户名获取个人信息
     * @param string $username
     * @return array
     */
    public function getUserInfoByUsername(string $username): array
    {
        $result = [];
        $data = $this->raw("select * from $this->userTable where username = ?", [$username]);
        if (empty($data[0])) {
            $result['status'] = -1;
            $result['errorMsg'] = '查询失败';
        } else {
            $result = $data[0];
            $result['like_type'] = explode(',', $data[0]['like_type']);
            $result['status'] = 0;
        }
        return $result;
    }

    /**
     * 修改用户信息并更新token
     * @param $username
     * @param $avatar
     * @param $desc
     * @return array
     * @throws \Exception
     */
    public function changeUserInfo($username, $avatar, $desc)
    {
        $returnResult = ['status' => 0, 'errorMsg' => '修改成功'];
        $sql = "update $this->userTable set `desc` = ? ";
        $params = [$desc];
        if ($avatar != '') {
            $sql .= ", avatar = ? ";
            $params[] = $avatar;
        }
        $sql .= " where username = ?";
        $params[] = $username;
        $res = $this->raw($sql, $params);
        if (!$res) {
            $returnResult['status'] = -1;
            $returnResult['errorMsg'] = '修改失败';
        }
        $returnResult['token'] = $this->refreshToken($username);
        return $returnResult;
    }

    /**
     * 根据用户名获取头像
     * @param $username
     * @return array
     */
    public function getAvatarByUsername($username)
    {
        $data = $this->raw("select avatar from $this->userTable where username = ?", [$username]);
        return $data[0]['avatar'] ?? [];
    }

    /**
     * 更新token
     * @param string $username
     * @return bool|string
     * @throws \Exception
     */
    public function refreshToken(string $username)
    {
        $result = $this->raw("select * from $this->userTable where username = ?", [$username]);
        if (empty($result[0])) {
            return false;
        } else {
            $result[0]['logintime'] = time();
            $token = JwtAuth::getInstance()->encode($result[0]);
            return $token;
        }
    }

    /**
     * 更新用户名对应的fd
     * @param $username
     * @param $fd
     * @return mixed|null
     */
    public function refreshUserFd($username, $fd)
    {
        return Redis::invoke('music', function (RedisClient $client) use ($username, $fd) {
            $fdKey = 'USER:' . $username;
            setExpire($client, $fdKey);
            return $client->set($fdKey, $fd);
        });
    }
}
