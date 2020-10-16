<?php

namespace App\Service;

use EasySwoole\Component\Singleton;
use GuzzleHttp\Client;

class TianTianApi {

    use Singleton;

    protected $host = 'http://fundgz.1234567.com.cn/';

    protected $timeout = 10;

    public function request($path, $method = 'GET', $postParams = []) {
        $content = [];
        $url = $this->host . $path;
        try {
            $client = new Client([
                'base_uri' => $this->host,
                'timeout' => $this->timeout
            ]);
            $content = $client->request($method, $url, [
                'form_params' => $postParams
            ]);
            $content = $content->getBody()->getContents();
            $content = ltrim($content, "jsonpgz(");
            $content = rtrim($content, ");");
            $content =  json_decode($content, true);
        } catch (\Exception $exception) {
            echo "接口调用异常：" . $exception->getMessage() . PHP_EOL;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            echo "GuzzleHttp接口调用错误：" . $e->getMessage() . PHP_EOL;
        }
        return $content;
    }
}
