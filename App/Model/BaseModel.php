<?php

namespace App\Model;

use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Mysqli\Client;
use EasySwoole\Mysqli\Config as MysqliConfig;

class BaseModel
{
    protected $mysql;

    public function __construct()
    {
        $this->mysql = new Client(new MysqliConfig(Config::getInstance()->getConf('mysqli')));
    }

    /**
     * 原生sql查询
     * @param string $sql
     * @param array $param
     * @return array|bool|null
     */
    public function raw(string $sql, array $param)
    {
        $this->mysql->queryBuilder()->raw($sql, $param);
        try {
            return $this->mysql->execBuilder();
        } catch (\Throwable $e) {
            Logger::getInstance()->log('数据库查询异常:' . $e->getMessage(), Logger::LOG_LEVEL_ERROR, 'MYSQL');
            return false;
        }
    }

    /**
     * 查询表中所有数据
     * @param string $tableName
     * @return array|bool|null
     */
    public function getAll(string $tableName)
    {
        $this->mysql->queryBuilder()->get($tableName);
        try {
            return $this->mysql->execBuilder();
        } catch (\Throwable $e) {
            Logger::getInstance()->log('数据库查询异常:' . $e->getMessage(), Logger::LOG_LEVEL_ERROR, 'MYSQL');
            return [];
        }
    }

    /**
     * 插入一条数据
     * @param string $tableName
     * @param array $insertData
     * @return bool
     */
    public function insert(string $tableName, array $insertData): bool
    {
        $this->mysql->queryBuilder()->insert($tableName, $insertData);
        try {
            return $this->mysql->execBuilder();
        } catch (\Throwable $e) {
            Logger::getInstance()->log('数据库新增异常:' . $e->getMessage(), Logger::LOG_LEVEL_ERROR, 'MYSQL');
            return false;
        }
    }
}
