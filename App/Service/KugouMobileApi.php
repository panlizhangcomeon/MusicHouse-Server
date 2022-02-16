<?php

namespace App\Service;

use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Config;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class KugouMobileApi
{
    use Singleton;

    private $host;

    private $timeout;

    public function __construct()
    {
        $options = Config::getInstance()->getConf('kugouMobile');
        $this->host = $options['host'];
        $this->timeout = $options['timeout'];
    }

    /**
     * 根据关键字搜索歌曲
     * @param $keyword
     * @param int $page
     * @param int $pageSize
     * @param string $format
     * @param int $showType
     * @return array
     */
    public function searchMusic($keyword, $page = 0, $pageSize = 50, $format = 'json', $showType = 1)
    {
        $result = [];
        $getParams = [
            'keyword' => $keyword,
            'page' => $page,
            'pagesize' => $pageSize,
            'format' => $format,
            'showtype' => $showType
        ];
        $path = 'api/v3/search/song';
        $data = $this->request($path, 'GET', $getParams);
        if (!empty($data['status']) && $data['status'] == 1 && !empty($data['data']['info'])) {
            foreach ($data['data']['info'] as $item) {
                if (!deepInArray($item['duration'], $result)) {
                    $result[] = [
                        'songname_original' => $item['songname_original'],
                        'songname' => $item['songname'],
                        'singername' => $item['singername'],
                        'hash' => $item['hash'],
                        'duration' => getFormatDuration($item['duration']),
                        'album_name' => empty($item['album_name']) ? '' : '《' . $item['album_name'] . '》',
                        'album_id' => $item['album_id'] ?? 0
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * 请求通用处理方法
     * @param $path
     * @param string $method
     * @param array $getParams
     * @param array $postParams
     * @return array|mixed|ResponseInterface|string
     */
    public function request($path, $method = 'GET', $getParams = [], $postParams = [])
    {
        $content = [];
        $requestUrl = $path . '?' . http_build_query($getParams);
        try {
            $client = new Client([
                'base_uri' => $this->host,
                'timeout' => $this->timeout
            ]);
            $content = $client->request($method, $requestUrl, [
                'form_params' => $postParams
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
