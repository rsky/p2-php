<?php
/**
 * rep2expack - RSSユーティリティ関数
 */

// {{{ rss_get_save_path()

/**
 * RSSのURLからローカルに保存するファイルのパスを設定する
 */
function rss_get_save_path($remotefile)
{
    global $_conf;
    static $finished = array();

    $remotefile = preg_replace('|^feed://|', 'http://', $remotefile);

    if (array_key_exists($remotefile, $finished)) {
        return $finished[$remotefile];
    }

    $pURL = @parse_url($remotefile);
    if (!$pURL || !isset($pURL['scheme']) || $pURL['scheme'] != 'http' || !isset($pURL['host'])) {
        $errmsg = 'p2 error: 不正なRSSのURL (' . p2h($remotefile) . ')';
        $error = PEAR::raiseError($errmsg);
        return ($finished[$remotefile] = $error);
    }

    $localname = '';
    if (isset($pURL['path']) && $pURL['path'] !== '') {
        $localname .= preg_replace('/[^\w.]/', '_', substr($pURL['path'], 1));
    }
    if (isset($pURL['query']) && $pURL['query'] !== '') {
        $localname .= '_' . preg_replace('/[^\w.%]/', '_', $pURL['query']) . '.rdf';
    }

    // 拡張子.cgiや.php等で保存するのを防ぐ
    if (!preg_match('/\.(rdf|rss|xml)$/i', $localname)) {
        // 古いバージョンで取得したRSSを削除
        if (file_exists($localname)) {
            @unlink($localname);
        }
        // 拡張子.rdfを付加
        $localname .= '.rdf';
    }
    // dotFileが作られるのを防ぐ
    if (substr($localname, 0, 1) == '.') {
        $localname = '_' . $localname;
    }

    $localpath = $_conf['dat_dir'] . '/p2_rss/' . $pURL['host'] . '/' . $localname;

    return ($finished[$remotefile] = $localpath);
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
