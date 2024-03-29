<?php

namespace App\HttpController;

use App\Service\KugouMobileApi;
use App\Service\KugouWebApi;

class Music extends BaseController
{
    /**
     * 歌曲列表
     */
    public function getMusicList()
    {
        $keyword = $this->input('keyword');
        $musicList = KugouMobileApi::getInstance()->searchMusic($keyword);
        $this->writeJson(200, $musicList, 'success');
    }

    /**
     * 获取歌曲详情
     */
    public function getMusic()
    {
        $hash = $this->input('hash');
        $albumId = $this->input('album_id', 0);
        $musicInfo = KugouWebApi::getInstance()->getMusic($hash, $albumId);
        $this->writeJson(200, $musicInfo, 'success');
    }
}
