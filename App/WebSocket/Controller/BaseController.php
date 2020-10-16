<?php
namespace App\WebSocket\Controller;

use App\Model\RoomModel;
use App\Service\JwtAuth;
use App\Service\PubSub;
use EasySwoole\Component\TableManager;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Socket\AbstractInterface\Controller;

class BaseController extends Controller {

    private $arguments = [];

    private $loginInfo = [];

    private $updateToken = 5 * 60;

    /**
     * 获取客户端文件描述符
     * @return mixed
     */
    public function getClientFd() {
        return $this->caller()->getClient()->getFd();
    }

    /**
     * 获取请求数据并保存
     * @param string|null $actionName
     * @return bool
     */
    protected function onRequest(?string $actionName): bool {
        if (!parent::onRequest($actionName)) {
            return false;
        }
        $this->arguments = $this->caller()->getArgs();
        return true;
    }

    /**
     * 登陆预处理
     * @return bool
     */
    protected function preHandleLogin() {
        $fd = $this->getClientFd();
        list($loginInfo, $newToken) = $this->checkLogin();
        if (!$loginInfo) {
            $returnResult = ['status' => -99, 'action' => 'no login'];
            $roomid = $this->getArgument('roomid');
            $username = $this->getLoginInfo('username');
            if (!empty($roomid) && !empty($username)) {
                //如果有房间id和用户名信息，则退出当前房间
                $roomModel = new RoomModel();
                $roomModel->quitRoom($roomid, $username);
            }
            $this->response()->setMessage(json_encode($returnResult));
            return false;
        }
        $this->loginInfo = $loginInfo;
        if (!empty($newToken)) {
            //通知前端更新token
            $updateToken = ['action' => 'updateToken', 'token' => $newToken, 'fd' => $fd];
            PubSub::getInstance()->publish('room', $updateToken);
        }
        return true;
    }

    /**
     * 判断登陆状态
     * @return array|bool
     */
    private function checkLogin() {
        $token = $this->getArgument('token', '');
        try {
            $loginInfo = JwtAuth::getInstance()->decode($token);
            $newToken = '';
            //token在有效期并且超过五分钟则重新刷新token
            if (time() - $loginInfo['logintime'] > $this->updateToken) {
                $loginInfo['logintime'] = time();
                $newToken = JwtAuth::getInstance()->encode($loginInfo);
            }
            return [$loginInfo, $newToken];
        } catch (\Throwable $throwable) {
            Logger::getInstance()->log('JWT解码错误:' . $throwable->getMessage(), Logger::LOG_LEVEL_ERROR, 'JWT');
            return false;
        }
    }

    /**
     * 获取请求参数
     * @param $name
     * @param null $default
     * @return mixed|null
     */
    public function getArgument($name, $default = null) {
        return $this->arguments[$name] ?? $default;
    }

    /**
     * 获取登陆信息
     * @param string|null $field
     * @return array|mixed|null
     */
    public function getLoginInfo(string $field = null) {
        if (is_null($field)) {
            return $this->loginInfo;
        }
        return $this->loginInfo[$field] ?? null;
    }
}
