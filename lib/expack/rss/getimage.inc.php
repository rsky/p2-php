<?php
/**
 * rep2expck - RSS画像キャッシュ
 */

require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';

// {{{ rss_get_image()

/**
 * イメージキャッシュのURLと画像サイズを返す
 */
function rss_get_image($src_url, $memo='')
{
    static $cache = array();

    $key = md5(serialize(func_get_args()));

    if (!isset($cache[$key])) {
        $cache[$key] = rss_get_image_ic2($src_url, $memo);
    }

    return $cache[$key];
}

// }}}
// {{{ rss_get_image_ic2()

/**
 * イメージキャッシュのURLと画像サイズを返す (ImageCache2)
 */
function rss_get_image_ic2($src_url, $memo='')
{
    static $thumbnailer = null;
    static $thumbnailer_k = null;

    if (is_null($thumbnailer)) {
        $thumbnailer = new ImageCache2_Thumbnailer(ImageCache2_Thumbnailer::SIZE_PC);
        $thumbnailer_k = new ImageCache2_Thumbnailer(ImageCache2_Thumbnailer::SIZE_MOBILE);
    }

    $icdb = new ImageCache2_DataObject_Images();

    if ($thumbnailer->ini['General']['automemo'] && $memo !== '') {
        $img_memo = ImageCache2_Normalizer::normalize($memo, 'CP932');
        if ($memo !== '') {
            $img_memo_query = '&amp;' . $_conf['detect_hint_q_utf8'];
            $img_memo_query .= '&amp;memo=' . rawurlencode($img_memo);
        } else {
            $img_memo = null;
            $img_memo_query = '';
        }
    } else {
        $img_memo = null;
        $img_memo_query = '';
    }

    $url_en = rawurlencode($src_url);

    // 画像表示方法
    //   r=0:HTML;r=1:リダイレクト;r=2:PHPで表示
    //   インライン表示用サムネイルはオリジナルがキャッシュされているとURLが短くなるのでr=2
    //   携帯用サムネイル（全画面表示が目的）はインライン表示しないのでr=0
    // サムネイルの大きさ
    //   t=0:オリジナル;t=1:PC用サムネイル;t=2:携帯用サムネイル;t=3:中間イメージ
    $img_url = 'ic2.php?r=1&amp;uri=' . $url_en;
    $img_size = '';
    $thumb_url = 'ic2.php?r=2&amp;t=1&amp;uri=' . $url_en;
    $thumb_url2 = 'ic2.php?r=2&amp;t=1&amp;id=';
    $thumb_size = '';
    $thumb_k_url = 'ic2.php?r=0&amp;t=2&amp;uri=' . $url_en;
    $thumb_k_url2 = 'ic2.php?r=0&amp;t=1&amp;id=';
    $thumb_k_size = '';
    $src_exists = false;

    // DBに画像情報が登録されていたとき
    if ($icdb->get($src_url)) {

        // ウィルスに感染していたファイルのとき
        if ($icdb->mime == 'clamscan/infected') {
            $aborn_img = array('./img/x04.png', 'width="32" height="32"');
            return array($aborn_img, $aborn_img, $aborn_img, P2_IMAGECACHE_ABORN);
        }
        // あぼーん画像のとき
        if ($icdb->rank < 0) {
            $virus_img = array('./img/x01.png', 'width="32" height="32"');
            return array($virus_img, $virus_img, $virus_img, P2_IMAGECACHE_VIRUS);
        }

        // オリジナルがキャッシュされているときは画像を直接読み込む
        $_img_path = $thumbnailer->srcPath($icdb->size, $icdb->md5, $icdb->mime);
        if (file_exists($_img_path)) {
            $img_url = $thumbnailer->srcUrl($icdb->size, $icdb->md5, $icdb->mime);
            $img_size = "width=\"{$icdb->width}\" height=\"{$icdb->height}\"";
            $src_exists = true;
        }

        // サムネイルが作成されていているときは画像を直接読み込む
        $_thumb_path = $thumbnailer->thumbPath($icdb->size, $icdb->md5, $icdb->mime);
        if (file_exists($_thumb_path)) {
            $thumb_url = $thumbnailer->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);
            // 自動タイトルメモ機能がONでタイトルが記録されていないときはDBを更新
            if (!is_null($img_memo) && strpos($icdb->memo, $img_memo) === false){
                $update = new ImageCache2_DataObject_Images();
                if (!is_null($icdb->memo) && strlen($icdb->memo) > 0) {
                    $update->memo = $img_memo . ' ' . $icdb->memo;
                } else {
                    $update->memo = $img_memo;
                }
                $update->whereAddQuoted('uri', '=', $src_url);
                $update->update();
            }
        } elseif ($src_exists) {
            $thumb_url = $thumb_url2 . $icdb->id;
        }

        // 携帯用サムネイルが作成されていているときは画像を直接読み込む
        $_thumb_k_path = $thumbnailer_k->thumbPath($icdb->size, $icdb->md5, $icdb->mime);
        if (file_exists($_thumb_k_path)) {
            $thumb_k_url = $thumbnailer_k->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);
        } elseif ($src_exists) {
            $thumb_k_url = $thumb_k_url2 . $icdb->id;
        }

        // サムネイルの画像サイズ
        $thumb_size = $thumbnailer->calc($icdb->width, $icdb->height);
        $thumb_size = preg_replace('/(\d+)x(\d+)/', 'width="$1" height="$2"', $thumb_size);

        // 携帯用サムネイルの画像サイズ
        $thumb_k_size = $thumbnailer_k->calc($icdb->width, $icdb->height);
        $thumb_k_size = preg_replace('/(\d+)x(\d+)/', 'width="$1" height="$2"', $thumb_k_size);

    // 画像がキャッシュされていないとき
    // 自動タイトルメモ機能がONならクエリにUTF-8エンコードしたタイトルを含める
    } else {
        $img_url .= $img_memo_query;
        $thumb_url .= $img_memo_query;
        $thumb_k_url .= $img_memo_query;
    }

    $result = array();
    $result[] = array($img_url, $img_size);
    $result[] = array($thumb_url, $thumb_size);
    $result[] = array($thumb_k_url, $thumb_k_size);
    $result[] = P2_IMAGECACHE_OK;

    return $result;
}

// }}}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
