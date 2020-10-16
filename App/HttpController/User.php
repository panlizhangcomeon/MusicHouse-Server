<?php
namespace App\HttpController;

use App\Model\RoomModel;
use App\Model\UserModel;
use EasySwoole\EasySwoole\Config;
use Ramsey\Uuid\Uuid;

class User extends BaseController {

    public function onRequest(?string $action): ?bool {
        if (!parent::onRequest($action)) {
            return false;
        }
        return true;
    }

    /**
     * 登陆
     */
    public function login() {
        $userModel = new UserModel();
        $username = $this->input('username', '');
        $password = $this->input('password', '');
        $result = $userModel->login($username, $password);
        $this->writeJson(200, $result, 'success');
    }

    /**
     * 注册
     */
    public function register() {
        $userModel = new UserModel();
        $username = $this->input('username', '');
        $password = $this->input('password', '');
        $area = $this->input('area', '');
        $birthday = $this->input('birthday', '');
        $likeType = $this->input('like_type', '');
        $likeTypeStr = implode(',', $likeType);
        $sex = $this->input('sex', 0);
        $desc = $this->input('desc', '');
        $result = $userModel->register($username, $password, $area, $birthday, $likeTypeStr, $sex, $desc);
        $this->writeJson(200, $result, 'success');
    }

    /**
     * 获取用户信息(根据token)
     */
    public function getUserInfo() {
        $userModel = new UserModel();
        $token = $this->input('token', '');
        $userInfo = $userModel->getUserInfo($token);
        $this->writeJson(200, $userInfo, 'success');
    }

    /**
     * 查询他人信息
     */
    public function getOtherInfo() {
        $userModel = new UserModel();
        $username = $this->input('username', '');
        $userInfo = $userModel->getUserInfoByUsername($username);
        $this->writeJson(200, $userInfo, 'success');
    }

    /**
     * 修改用户信息
     */
    public function changeUserInfo() {
        $userModel = new UserModel();
        $username = $this->input('username', '');
        $avatar = $this->input('avatar', '');
        $desc = $this->input('desc', '');
        $result = $userModel->changeUserInfo($username, $avatar, $desc);
        $this->writeJson(200, $result, 'success');
    }

    /**
     * 修改密码
     */
    public function changePassWd() {
        $userModel = new UserModel();
        $username = $this->input('username', '');
        $oldPassword = $this->input('oldpassword', '');
        $newPassword = $this->input('newpassword', '');
        $result = $userModel->changePassword($username, $oldPassword, $newPassword);
        $this->writeJson(200, $result, 'success');
    }

    /**
     * 上传用户头像
     */
    public function uploadAvatar() {
        $request = $this->request();
        $uuid = Uuid::uuid1()->toString();
        $imgFileObj = $request->getUploadedFile('avatar');
        $avatarPath = Config::getInstance()->getConf('avatar_path');
        $avatarName = 'avatar_' . $uuid . '.jpg';
        $avatarPath = $avatarPath . $avatarName;
        $imgFileObj->moveTo($avatarPath);
        $this->writeJson(200, ['avatarUrl' => $avatarPath], 'success');
    }

    /**
     * 获取服务器图片
     */
    public function img() {
        $path = $this->input('imgPath', '');
        if (empty($path)) {
            return false;
        }
        $img = file_get_contents($path, true);
        $imgInfo = getimagesize($path);
        $this->response()->withHeader('Content-Type', $imgInfo['mime']);
        $this->response()->write($img);
    }

    /**
     * 退出登陆
     */
    public function logout() {
        $roomId = $this->input('roomid');
        $username = $this->input('username', '');
        $roomModel = new RoomModel();
        $result = ['status' => 0, 'errorMsg' => '注销成功'];
        if (!empty($roomId)) {
            $result = $roomModel->quitRoom($roomId, $username);
        }
        $this->writeJson(200, $result, 'success');
    }
}
