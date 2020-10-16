<?php

namespace App\Service;

use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

class KugouWebApi {

    use Singleton;

    private $host;

    private $timeout;

    public function __construct() {
        $options = Config::getInstance()->getConf('kugouWeb');
        $this->host = $options['host'];
        $this->timeout = $options['timeout'];
    }

    /**
     * 获取音乐详情(带歌词)
     * @param $hash
     * @param string $r
     * @return array
     */
    public function getMusic($hash, $r = 'play/getdata') {
        $result = ['status' => -1, 'errorMsg' => '获取失败'];
        $getParams = [
            'r' => $r,
            'hash' => $hash,
        ];
        $path = 'yy/index.php';
        $header = ['cookie' => 'kg_mid=' . time()];
        $data = $this->request($path, 'GET', $getParams, [], $header);
        if (!empty($data['status']) && $data['status'] == 1 && !empty($data['data'])) {
            $result = [
                'play_url' => $data['data']['play_url'],
                'lyrics' => $this->getLyricsArr($data['data']['lyrics']),
                'audio_name' => $data['data']['audio_name'],
                'status' => 0,
                'errorMsg' => '获取成功'
            ];
        }
        return $result;
    }

    /**
     * 拼接歌词数组
     * @param string $lyrics
     * @return array
     */
    private function getLyricsArr(string $lyrics) {
        $arr = explode("\r\n", $lyrics);
        $data = [];
        foreach ($arr as $item) {
            $start = strpos($item, '[');
            $end = strrpos($item, ']');
            $timeStr = substr($item, $start + 1, $end - 1);
            $lyric = substr($item, $end + 1);
            if (substr($timeStr, 0, 1) == '0') {
                $minute = substr($timeStr, 1, 1);
                $seconds = substr($timeStr, 3);
                if (substr($seconds, 0, 1) == '0') {
                    $seconds = substr($seconds, 1);
                }
                $time = $minute * 60 + $seconds;
                $data[] = [
                    'time' => $time,
                    'lyric' => $lyric
                ];
            }
        }
        return $data;
    }

    /**
     * 请求通用处理方法
     * @param $path
     * @param string $method
     * @param array $getParams
     * @param array $postParams
     * @param array $headerParams
     * @return array|mixed|ResponseInterface|string
     */
    public function request($path, $method = 'GET', $getParams = [], $postParams = [], $headerParams = []) {
        $content = [];
        $requestUrl = $path . '?' . http_build_query($getParams);
        try {
            $client = new Client([
                'base_uri' => $this->host,
                'timeout' => $this->timeout
            ]);
            $content = $client->request($method, $requestUrl, [
                'form_params' => $postParams,
                'headers' => $headerParams
            ]);
            $content = $content->getBody()->getContents();
            $content =  json_decode($content, true);
        } catch (\Exception $exception) {
            echo "接口调用异常：" . $exception->getMessage() . PHP_EOL;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            echo "GuzzleHttp接口调用错误：" . $e->getMessage() . PHP_EOL;
        }
        return $content;
    }
}
