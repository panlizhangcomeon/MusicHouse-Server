<?php
return [
    'SERVER_NAME' => "EasySwoole",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9501,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SOCKET_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER,EASYSWOOLE_REDIS_SERVER
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 8,
            'reload_async' => true,
            'max_wait_time'=>3
        ],
        'TASK'=>[
            'workerNum'=>4,
            'maxRunningNum'=>128,
            'timeout'=>15
        ]
    ],
    'TEMP_DIR' => '/tmp/swoole/Fund',
    'LOG_DIR' => null,
    "crosOptions" => [
        //是否开启跨域主持
        'enable_cros' => true,
        //跨域支持域名，精确匹配
        'allow_origin' => [
            '*'
        ],
        //客户端提交的请求头中哪些头可以被服务端获取
        'allow_headers' => 'Content-Type, Authorization, X-Requested-With, authToken',
        //允许被使用的http方法
        'allow_methods' => 'GET, POST, OPTIONS',
        //允许附带cookie和http认证信息
        'allow_credentials' => true,
    ],
    'kugouMobile' => [
        'host' => 'http://mobilecdn.kugou.com/',
        'timeout' => 120
    ],
    'kugouWeb' => [
        'host' => 'http://www.kugou.com/',
        'timeout' => 120
    ],
    'mysqli' => [
        'host'          => '172.17.20.88',
        'port'          => 3307,
        'user'          => 'root',
        'password'      => 'root',
        'database'      => 'music',
        'timeout'       => 5,
        'charset'       => 'utf8mb4',
    ],
    'jwt' => [
        'key' => 'example_key',
        'expire' => 7200,
    ],
    'redis' => [
        'host' => '172.17.20.88',
        'port' => 6390,
        'auth' => '',
        'db' => 0,
        'timeout' => 10,
    ],
    'avatar_path' => EASYSWOOLE_ROOT . '/upload/'
];
