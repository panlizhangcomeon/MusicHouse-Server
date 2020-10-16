<?php
namespace EasySwoole\EasySwoole;

use App\Crontab\TaskFile;
use App\Process\FundProcess;
use App\Process\NoticeProcess;
use App\WebSocket\WebSocketEvent;
use App\WebSocket\WebSocketParser;
use EasySwoole\Component\Process\Manager;
use EasySwoole\Component\TableManager;
use EasySwoole\EasySwoole\Crontab\Crontab;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Http\Message\Status;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Redis\Config\RedisConfig;
use EasySwoole\RedisPool\Redis;
use EasySwoole\Socket\Dispatcher;
use Swoole\Table;

class EasySwooleEvent implements Event
{

    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');
    }

    public static function mainServerCreate(EventRegister $register)
    {
        // 查看基金估值并格式化输出
        //Manager::getInstance()->addProcess(new FundProcess());

        /**
         * **************** 注册通知进程**********************
         */
        Manager::getInstance()->addProcess(new NoticeProcess());

        /**
         * **************** websocket控制器 **********************
         */
        // 创建一个 Dispatcher 配置
        $conf = new \EasySwoole\Socket\Config();
        // 设置 Dispatcher 为 WebSocket 模式
        $conf->setType(\EasySwoole\Socket\Config::WEB_SOCKET);
        // 设置解析器对象
        $conf->setParser(new WebSocketParser());
        // 创建 Dispatcher 对象 并注入 config 对象
        $dispatch = new Dispatcher($conf);
        // 给server 注册相关事件 在 WebSocket 模式下  on message 事件必须注册 并且交给 Dispatcher 对象处理
        $register->set(EventRegister::onMessage, function (\swoole_websocket_server $server, \swoole_websocket_frame $frame) use ($dispatch) {
            $dispatch->dispatch($server, $frame->data, $frame);
        });

        //自定义握手事件
        $websocketEvent = new WebSocketEvent();
        $register->set(EventRegister::onHandShake, function (\swoole_http_request $request, \swoole_http_response $response) use ($websocketEvent) {
            $websocketEvent->onHandShake($request, $response);
        });

        //自定义关闭事件
        $register->set(EventRegister::onClose, function (\swoole_server $server, int $fd, int $reactorId) use ($websocketEvent) {
            $websocketEvent->onClose($server, $fd, $reactorId);
        });

        /**
         * **************** 注册redis连接池 **********************
         */
        Redis::getInstance()->register('music', new RedisConfig(Config::getInstance()->getConf('redis')));

        /**
         * **************** 注册Crontab **********************
         */
        Crontab::getInstance()->addTask(TaskFile::class);
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        // TODO: Implement onRequest() method.
        $crosOptions = Config::getInstance()->getConf("crosOptions");
        if (!empty($crosOptions['enable_cros'])) {
            $origin = $request->getHeader("origin");
            $origin = $origin[0] ?? '';
            if (in_array($origin, $crosOptions['allow_origin']) || in_array('*', $crosOptions['allow_origin'])) {
                $response->withHeader("Access-Control-Allow-Origin", $origin);
                $response->withHeader("Access-Control-Allow-Headers", $crosOptions['allow_headers']);
                $response->withHeader("Access-Control-Allow-Methods", $crosOptions['allow_methods']);
                $response->withHeader("Access-Control-Allow-Credentials", $crosOptions['allow_credentials']);
                if ($request->getMethod() === 'OPTIONS') {
                    $response->withStatus(Status::CODE_OK);
                    return false;
                }
                return true;
            }
        }
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterAction() method.
    }
}
