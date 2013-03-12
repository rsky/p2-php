<?php

class P2UtilWiki
{
    /**
     * +Wiki:プロフィールIDからBEIDを計算する
     *
     * @return integer|0 成功したらBEIDを返す。失敗したら0を返す。
     */
    public static function calcBeId($prof_id)
    {
        for ($y = 2; $y <= 9; $y++) {
            for ($x = 2; $x <= 9; $x++) {
                $id = (($prof_id - $x*10.0 - $y)/100.0 + $x - $y - 5.0)/(3.0 * $x * $y);
                if ($id == floor($id)) {
                    return $id;
                }
            }
        }
        return 0;
    }

    /**
     * Wiki:そのURLにアクセスできるか確認する
     */
    public static function isURLAccessible($url, $timeout = 7)
    {
        $code = self::getResponseCode($url);
        return ($code == 200 || $code == 206) ? true : false;
    }

    /**
     * URLがイメピタならtrueを返す
     */
    public static function isUrlImepita($url)
    {
        return preg_match('{^http://imepita\.jp/}', $url);
    }

    public static function getResponseCode($url)
    {
        $client = new HTTP_Client();
        $client->setRequestParameter('timeout', $timeout);
        $client->setDefaultHeader('User-Agent', 'Monazilla/1.00');
        if (!empty($_conf['proxy_use'])) {
            $client->setRequestParameter('proxy_host', $_conf['proxy_host']);
            $client->setRequestParameter('proxy_port', $_conf['proxy_port']);
        }
        return $client->head($url);
    }

    /**
     * Wiki:Last-Modifiedをチェックしてキャッシュする
     * time:チェックしない期間(unixtime)
     */
    public static function cacheDownload($url, $path, $time = 0)
    {
        $filetime = @filemtime($path);

        // キャッシュ有効期間ならダウンロードしない
        if ($filetime !== false && $filetime > time() - $time) {
            return;
        }

        // 新しければ取得
        P2Util::fileDownload($url, $path);
    }
}
