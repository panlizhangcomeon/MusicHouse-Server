<?php
namespace App\WebSocket\Controller;

class Index extends BaseController {
    /**
     * 心跳检测
     */
    public function heart() {
        $this->response()->setMessage('PONG');
    }
}
