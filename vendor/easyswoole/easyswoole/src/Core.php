<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:07
 */

namespace EasySwoole\EasySwoole;


use EasySwoole\Component\Di;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Manager;
use EasySwoole\Component\Singleton;
use EasySwoole\Component\TableManager;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Bridge\Bridge;
use EasySwoole\EasySwoole\Crontab\Crontab;
use EasySwoole\EasySwoole\Swoole\EventHelper;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\Dispatcher;
use EasySwoole\Http\Message\Status;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Log\LoggerInterface;
use EasySwoole\Trigger\Location;
use EasySwoole\Trigger\TriggerInterface;
use EasySwoole\Utility\File;
use EasySwoole\Log\Logger as DefaultLogger;
use EasySwoole\Trigger\Trigger as DefaultTrigger;
use EasySwoole\Task\Config as TaskConfig;
use Swoole\Server;
use Swoole\Timer;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response  as SwooleResponse;
use Swoole\Event as SwooleEvent;

////////////////////////////////////////////////////////////////////
//                          _ooOoo_                               //
//                         o8888888o                              //
//                         88" . "88                              //
//                         (| ^_^ |)                              //
//                         O\  =  /O                              //
//                      ____/`---'\____                           //
//                    .'  \\|     |//  `.                         //
//                   /  \\|||  :  |||//  \                        //
//                  /  _||||| -:- |||||-  \                       //
//                  |   | \\\  -  /// |   |                       //
//                  | \_|  ''\---/''  |   |                       //
//                  \  .-\__  `-`  ___/-. /                       //
//                ___`. .'  /--.--\  `. . ___                     //
//            \  \ `-.   \_ __\ /__ _/   .-` /  /                 //
//      ========`-.____`-.___\_____/___.-`____.-'========         //
//                           `=---='                              //
//      ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^        //
//         佛祖保佑       永无BUG       永不修改                     //
////////////////////////////////////////////////////////////////////


class Core
{
    use Singleton;

    private $isDev = true;

    function __construct()
    {
        defined('SWOOLE_VERSION') or define('SWOOLE_VERSION',intval(phpversion('swoole')));
        defined('EASYSWOOLE_ROOT') or define('EASYSWOOLE_ROOT', realpath(getcwd()));
        defined('EASYSWOOLE_SERVER') or define('EASYSWOOLE_SERVER',1);
        defined('EASYSWOOLE_WEB_SERVER') or define('EASYSWOOLE_WEB_SERVER',2);
        defined('EASYSWOOLE_WEB_SOCKET_SERVER') or define('EASYSWOOLE_WEB_SOCKET_SERVER',3);
        defined('EASYSWOOLE_REDIS_SERVER') or define('EASYSWOOLE_REDIS_SERVER',4);
    }

    function setIsDev(bool $isDev)
    {
        $this->isDev = $isDev;
        return $this;
    }

    function isDev():bool
    {
        return $this->isDev;
    }

    function initialize()
    {
        //检查全局文件是否存在.
        $file = EASYSWOOLE_ROOT . '/EasySwooleEvent.php';
        if(file_exists($file)){
            require_once $file;
            try{
                $ref = new \ReflectionClass('EasySwoole\EasySwoole\EasySwooleEvent');
                if(!$ref->implementsInterface(Event::class)){
                    die('global file for EasySwooleEvent is not compatible for EasySwoole\EasySwoole\AbstractInterface\Event');
                }
                unset($ref);
            }catch (\Throwable $throwable){
                die($throwable->getMessage());
            }
        }else{
            die('global event file missing');
        }
        //先加载配置文件
        $this->loadEnv();
        //临时文件和Log目录初始化
        $this->sysDirectoryInit();
        //注册错误回调
        $this->registerErrorHandler();
        return $this;
    }

    function globalInitialize():Core
    {
        //执行全局初始化事件
        EasySwooleEvent::initialize();
        return $this;
    }

    function createServer()
    {
        $conf = Config::getInstance()->getConf('MAIN_SERVER');
        ServerManager::getInstance()->createSwooleServer(
            $conf['PORT'],$conf['SERVER_TYPE'],$conf['LISTEN_ADDRESS'],$conf['SETTING'],$conf['RUN_MODEL'],$conf['SOCK_TYPE']
        );
        //hook 全局的mainServerCreate事件
        $ret = EasySwooleEvent::mainServerCreate(ServerManager::getInstance()->getMainEventRegister());
        //如果返回false,说明用户希望接管全部事件
        if($ret !== false){
            $this->registerDefaultCallBack(ServerManager::getInstance()->getSwooleServer(),$conf['SERVER_TYPE']);
        }
        $this->extraHandler();
        return $this;
    }

    function start()
    {
        //给主进程也命名
        $serverName = Config::getInstance()->getConf('SERVER_NAME');
        if(!in_array(PHP_OS,['Darwin','CYGWIN','WINNT'])){
            cli_set_process_title($serverName);
        }
        //启动
        ServerManager::getInstance()->start();
    }

    private function sysDirectoryInit():void
    {
        //创建临时目录    请以绝对路径，不然守护模式运行会有问题
        $tempDir = Config::getInstance()->getConf('TEMP_DIR');
        if(empty($tempDir)){
            $tempDir = EASYSWOOLE_ROOT.'/Temp';
            Config::getInstance()->setConf('TEMP_DIR',$tempDir);
        }else{
            $tempDir = rtrim($tempDir,'/');
        }
        if(!is_dir($tempDir)){
            File::createDirectory($tempDir);
        }
        defined('EASYSWOOLE_TEMP_DIR') or define('EASYSWOOLE_TEMP_DIR',$tempDir);

        $logDir = Config::getInstance()->getConf('LOG_DIR');
        if(empty($logDir)){
            $logDir = EASYSWOOLE_ROOT.'/Log';
            Config::getInstance()->setConf('LOG_DIR',$logDir);
        }else{
            $logDir = rtrim($logDir,'/');
        }
        if(!is_dir($logDir)){
            File::createDirectory($logDir);
        }
        defined('EASYSWOOLE_LOG_DIR') or define('EASYSWOOLE_LOG_DIR', $logDir);

        // 设置默认文件目录值(如果自行指定了目录则优先使用指定的)
        if (!Config::getInstance()->getConf('MAIN_SERVER.SETTING.pid_file')) {
            Config::getInstance()->setConf('MAIN_SERVER.SETTING.pid_file', $tempDir . '/pid.pid');
        }
        if (!Config::getInstance()->getConf('MAIN_SERVER.SETTING.log_file')) {
            Config::getInstance()->setConf('MAIN_SERVER.SETTING.log_file', $logDir . '/swoole.log');
        }
    }

    private function registerErrorHandler()
    {
        ini_set("display_errors", "On");
        error_reporting(E_ALL | E_STRICT);

        //初始化配置Logger
        $logger = Di::getInstance()->get(SysConst::LOGGER_HANDLER);
        if(!$logger instanceof LoggerInterface){
            $logger = new DefaultLogger(EASYSWOOLE_LOG_DIR);
        }
        Logger::getInstance($logger);

        //初始化追追踪器
        $trigger = Di::getInstance()->get(SysConst::TRIGGER_HANDLER);
        if(!$trigger instanceof TriggerInterface){
            $trigger = new DefaultTrigger(Logger::getInstance());
        }
        Trigger::getInstance($trigger);

        //在没有配置自定义错误处理器的情况下，转化为trigger处理
        $errorHandler = Di::getInstance()->get(SysConst::ERROR_HANDLER);
        if(!is_callable($errorHandler)){
            $errorHandler = function($errorCode, $description, $file = null, $line = null){
                $l = new Location();
                $l->setFile($file);
                $l->setLine($line);
                Trigger::getInstance()->error($description,$errorCode,$l);
            };
        }
        set_error_handler($errorHandler);

        $func = Di::getInstance()->get(SysConst::SHUTDOWN_FUNCTION);
        if(!is_callable($func)){
            $func = function (){
                $error = error_get_last();
                if(!empty($error)){
                    $l = new Location();
                    $l->setFile($error['file']);
                    $l->setLine($error['line']);
                    Trigger::getInstance()->error($error['message'],$error['type'],$l);
                }
            };
        }
        register_shutdown_function($func);
    }

    private function registerDefaultCallBack(\swoole_server $server,int $serverType)
    {
        /*
         * 注册默认回调
         */
        if(in_array($serverType,[EASYSWOOLE_WEB_SERVER,EASYSWOOLE_WEB_SOCKET_SERVER],true)){
            $namespace = Di::getInstance()->get(SysConst::HTTP_CONTROLLER_NAMESPACE);
            if(empty($namespace)){
                $namespace = 'App\\HttpController\\';
            }
            $depth = intval(Di::getInstance()->get(SysConst::HTTP_CONTROLLER_MAX_DEPTH));
            $depth = $depth > 5 ? $depth : 5;
            $max = intval(Di::getInstance()->get(SysConst::HTTP_CONTROLLER_POOL_MAX_NUM));
            if($max == 0){
                $max = 500;
            }
            $waitTime = intval(Di::getInstance()->get(SysConst::HTTP_CONTROLLER_POOL_WAIT_TIME));
            if($waitTime == 0){
                $waitTime = 5;
            }
            $dispatcher = new Dispatcher($namespace,$depth,$max);
            $dispatcher->setControllerPoolWaitTime($waitTime);
            $httpExceptionHandler = Di::getInstance()->get(SysConst::HTTP_EXCEPTION_HANDLER);
            if(!is_callable($httpExceptionHandler)){
                $httpExceptionHandler = function ($throwable,$request,$response){
                    $response->withStatus(Status::CODE_INTERNAL_SERVER_ERROR);
                    $response->write(nl2br($throwable->getMessage()."\n".$throwable->getTraceAsString()));
                    Trigger::getInstance()->throwable($throwable);
                };
                Di::getInstance()->set(SysConst::HTTP_EXCEPTION_HANDLER,$httpExceptionHandler);
            }
            $dispatcher->setHttpExceptionHandler($httpExceptionHandler);

            EventHelper::on($server,EventRegister::onRequest,function (SwooleRequest $request,SwooleResponse $response)use($dispatcher){
                $request_psr = new Request($request);
                $response_psr = new Response($response);
                try{
                    if(EasySwooleEvent::onRequest($request_psr,$response_psr)){
                        $dispatcher->dispatch($request_psr,$response_psr);
                    }
                }catch (\Throwable $throwable){
                    call_user_func(Di::getInstance()->get(SysConst::HTTP_EXCEPTION_HANDLER),$throwable,$request_psr,$response_psr);
                }finally{
                    try{
                        EasySwooleEvent::afterRequest($request_psr,$response_psr);
                    }catch (\Throwable $throwable){
                        call_user_func(Di::getInstance()->get(SysConst::HTTP_EXCEPTION_HANDLER),$throwable,$request_psr,$response_psr);
                    }
                }
                $response_psr->__response();
            });
        }

        $register = ServerManager::getInstance()->getMainEventRegister();
        //注册默认的worker start
        EventHelper::registerWithAdd($register,EventRegister::onWorkerStart,function (Server $server,$workerId){
            $serverName = Config::getInstance()->getConf('SERVER_NAME');
            $type = 'Unknown';
            if(($workerId < Config::getInstance()->getConf('MAIN_SERVER.SETTING.worker_num')) && $workerId >= 0){
                $type = 'Worker';
            }
            $processName = "{$serverName}.{$type}.{$workerId}";
            if(!in_array(PHP_OS,['Darwin','CYGWIN','WINNT'])){
                cli_set_process_title($processName);
            }
            $table = Manager::getInstance()->getProcessTable();
            $pid = getmypid();
            $table->set($pid,[
                'pid'=>$pid,
                'name'=>$processName,
                'group'=>"{$serverName}.Worker"
            ]);
            Timer::tick(1*1000,function ()use($table,$pid){
                $table->set($pid,[
                    'memoryUsage'=>memory_get_usage(),
                    'memoryPeakUsage'=>memory_get_peak_usage(true)
                ]);
            });
        });

        EventHelper::registerWithAdd($register,$register::onWorkerStop,function (){
            $table = Manager::getInstance()->getProcessTable();
            $pid = getmypid();
            $table->del($pid);
            Timer::clearAll();
            SwooleEvent::exit();
        });

        /*
         * 开启reload async的时候，清理事件
         */
        EventHelper::registerWithAdd($register,$register::onWorkerExit,function (){
            Timer::clearAll();
            SwooleEvent::exit();
        });
    }

    public function loadEnv()
    {
        //加载之前，先清空原来的
        if($this->isDev){
            $file  = EASYSWOOLE_ROOT.'/dev.php';
        }else{
            $file  = EASYSWOOLE_ROOT.'/produce.php';
        }
        Config::getInstance()->loadEnv($file);
    }

    private function extraHandler()
    {
        $serverName = Config::getInstance()->getConf('SERVER_NAME');
        //注册crontab进程
        Crontab::getInstance()->__run();
        //注册Task进程
        $config = Config::getInstance()->getConf('MAIN_SERVER.TASK');
        $config = new TaskConfig($config);
        $config->setTempDir(EASYSWOOLE_TEMP_DIR);
        $config->setServerName($serverName);
        $config->setOnException(function (\Throwable $throwable){
            Trigger::getInstance()->throwable($throwable);
        });
        $server = ServerManager::getInstance()->getSwooleServer();
        TaskManager::getInstance($config)->attachToServer($server);
        //初始化进程管理器
        Manager::getInstance()->attachToServer($server);
        //初始化Bridge
        Bridge::getInstance()->attachServer($server);
    }
}
