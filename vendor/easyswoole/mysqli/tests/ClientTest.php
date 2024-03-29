<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/11/22 0022
 * Time: 20:08
 */

namespace EasySwoole\Mysqli\Tests;


use EasySwoole\Mysqli\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    protected function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $config = new \EasySwoole\Mysqli\Config(MYSQL_CONFIG);

        $this->client = new \EasySwoole\Mysqli\Client($config);
    }

    public function testAdd()
    {
        $this->client->queryBuilder()->insert("user_test_list", [
            'name' => 'siam,你好',
            'age'  => 21,
            'addTime' => "2019-11-22 20:19:16",
            'state' => 1
        ]);

        $res = $this->client->execBuilder();
        $this->assertTrue($res !== null);
    }

    public function testGet()
    {

        $this->client->queryBuilder()->where('find_in_set(?, name)', ['siam'])->get('user_test_list');
        $res = $this->client->execBuilder();
        $this->assertEquals("siam,你好", $res[0]['name']);

        $this->client->queryBuilder()->where('find_in_set(?, name)', ['不存在的你'])->get('user_test_list');
        $res = $this->client->execBuilder();
        $this->assertEquals([], $res);

    }

    public function testInsertAll()
    {
        $this->client->queryBuilder()->insertAll("user_test_list", [
            [
                'name' => 'siam,你好',
                'age'  => 21,
                'addTime' => "2019-11-22 20:19:16",
                'state' => 1
            ],
            [
                'name' => 'siam,你好',
                'age'  => 21,
                'addTime' => "2019-11-22 20:19:16",
                'state' => 2
            ]
        ]);
        $res = $this->client->execBuilder();

        // var_dump($this->client->mysqlClient()->insert_id);
        // insert_id 是第一行的

        $this->assertTrue($res);
        $this->assertEquals($this->client->mysqlClient()->affected_rows, 2);
    }

    public function testDelete()
    {
        $this->client->queryBuilder()->delete("user_test_list");
        $res = $this->client->execBuilder();
        $this->assertTrue($res);
        $this->assertEquals($this->client->mysqlClient()->affected_rows, 3);
    }
}