<?php

namespace App\HttpController;

use EasySwoole\EasySwoole\Core;
use EasySwoole\Http\AbstractInterface\Controller;

class BaseController extends Controller
{
    /**
     * 获取请求参数
     * @param $name
     * @param null $default
     * @return array|mixed|object|null
     */
    protected function input($name, $default = null)
    {
        $param = $this->request()->getRequestParam($name);
        return $param ?? $default;
    }

    protected function onRequest(?string $action): ?bool
    {
        if (!parent::onRequest($action)) {
            return false;
        }
        return true;
    }

    protected function actionNotFound(?string $action)
    {
        parent::actionNotFound($action); // TODO: Change the autogenerated stub
    }

    protected function onException(\Throwable $throwable): void
    {
        if (Core::getInstance()->isDev()) {
            $this->writeJson(500, null, $throwable->getMessage());
        } else {
            $this->writeJson(500, null, "系统内部错误，请稍后重试");
        }
    }
}
