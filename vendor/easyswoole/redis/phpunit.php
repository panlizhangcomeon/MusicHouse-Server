<?php

defined("REDIS_HOST") ?: define('REDIS_HOST', '127.0.0.1');
defined("REDIS_PORT") ?: define('REDIS_PORT', 6379);
defined("REDIS_AUTH") ?: define('REDIS_AUTH', '');
defined("REDIS_UNIX_SOCKET") ?: define('REDIS_UNIX_SOCKET', '/tmp/redis.sock');

defined("REDIS_CLUSTER_SERVER_LIST") ?: define('REDIS_CLUSTER_SERVER_LIST',
    [
        ['127.0.0.1', 6380],
        ['127.0.0.1', 6381],
        ['127.0.0.1', 6382],
        ['127.0.0.1', 6383],
        ['127.0.0.1', 6384],
        ['127.0.0.1', 6385],
    ]);
defined("REDIS_CLUSTER_AUTH") ?: define('REDIS_CLUSTER_AUTH', '');
