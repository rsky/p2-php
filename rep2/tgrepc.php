<?php
/**
 * スレッドタイトル検索 tGrep クライアント
 *
 * http://page2.xrea.jp/tgrep/ を利用
 */

// {{{ p2基本設定読み込み&認証

define('P2_OUTPUT_XHTML', 1);

require_once __DIR__ . '/../init.php';

$_login->authorize();

// }}}
// {{{ 準備

if ($_conf['iphone'] && isset($_REQUEST['iq'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        require_once P2_LIB_DIR . '/menu_iphone.inc.php';
        $_GET['Q'] = menu_iphone_unicode_urldecode($_POST['iq']);
    } else {
        $_GET['Q'] = $_GET['iq'];
    }
    if (isset($_GET['ic'])) {
        unset($_GET['B'], $_GET['C'], $_GET['S'], $_GET['P'], $_GET['ib']);
    }
    $is_ajax = true;
} else {
    $is_ajax = false;
    if ($_conf['view_forced_by_query']) {
        output_add_rewrite_var('b', $_conf['b']);
    }
}


$query_params = array();
if (isset($_GET['Q']) && is_string($_GET['Q']) && strlen($_GET['Q']) > 0) {
    $query_params['q'] = $_GET['Q'];
    $query_params['n'] = $limit = ($_conf['ktai'] || $_conf['iphone']) ? '25' : '100';
    //$query_keys = array('s', 'b', 'c', 'o', 'n', 'p');
    $query_keys = array('s', 'b', 'c', 'p');
    foreach ($query_keys as $_k) {
        $_K = strtoupper($_k);
        if (isset($_GET[$_K])) {
            $_v = $_GET[$_K];
            if (is_string($_v) && strlen($_v) > 0 && $_v != '0') {
                $query_params[$_k] = $_v;
            } else {
                unset($_GET[$_K]);
            }
        }
    }
    mb_convert_variables('UTF-8', 'CP932', $query_params);

    ini_set('arg_separator.output', '&'); // ≒ ini_restore('arg_separator.output');
    $query = http_build_query($query_params);
    ini_set('arg_separator.output', '&amp;');
    $cache_options = array(
        'cacheDir' => $_conf['cache_dir'] . DIRECTORY_SEPARATOR . 'tgrep' . DIRECTORY_SEPARATOR,
        'lifeTime' => 3600,
        'fileNameProtection' => false,
        'automaticSerialization' => true,
    );
    if (!is_dir($cache_options['cacheDir'])) {
        FileCtl::mkdirRecursive($cache_options['cacheDir']);
    }
    $cache = new Cache_Lite($cache_options);
    $cache_id_result = md5($query);
    $cache_id_profile = md5($query_params['q']);
    $cache_group_result = 'tgrep2result';
    $cache_group_profile = 'tgrep2profile';
} else {
    $query = null;
}

// }}}
// {{{ 検索&キャッシュ

if ($query) {
    // キャッシュを取得
    $search_result = $cache->get($cache_id_result, $cache_group_result);
    $search_profile = $cache->get($cache_id_profile, $cache_group_profile);

    // キャッシュされていないか、結果セットと統計の更新タイムスタンプが異なるとき、tGrep サーバに問い合わせる
    if (!$search_result || !$search_profile || $search_profile['modified'] != $search_result['modified']) {
        if (!$search_profile || ($search_result && $search_profile['modified'] != $search_result['modified'])) {
            $query .= '&i=1';
        }
        $search_result = tgrep_search($query);
        if (!isset($search_result['profile']) && $search_profile && $search_profile['modified'] != $search_result['modified']) {
            if (substr($query, -4) != '&i=1') {
                $query .= '&i=1';
            }
            $search_result = tgrep_search($query);
        }
        if (isset($search_result['profile'])) {
            $search_profile = array('modified' => $search_result['modified'], 'profile' => $search_result['profile']);
            unset($search_result['profile']);
            mb_convert_variables('CP932', 'UTF-8', $search_profile);
            $cache->save($search_profile, $cache_id_profile, $cache_group_profile);
        }
        $regex = mb_convert_encoding($search_profile['profile']['regex'], 'UTF-8', 'CP932');
        if (!empty($search_result['threads'])) {
            foreach (array_keys($search_result['threads']) as $order) {
                $_title = preg_replace($regex, '<b class="filtering">$0</b>', $search_result['threads'][$order]->title);
                $search_result['threads'][$order]->title = preg_replace('|&(?=[^;]*</?b>)|u', '&amp;', $_title);
            }
        }
        mb_convert_variables('CP932', 'UTF-8', $search_result);
        $cache->save($search_result, $cache_id_result, $cache_group_result);
    }

    // 検索結果キャッシュのガーベッジコレクション
    if (mt_rand(0, 99) == 0) {
        P2Util::garbageCollection($cache_options['cacheDir'], $cache_options['lifeTime'], 'cache_' . $cache_group_result);
        P2Util::garbageCollection($cache_options['cacheDir'], $cache_options['lifeTime'], 'cache_' . $cache_group_profile);
    }

    $errors = (isset($search_result['errors'])) ? $search_result['errors'] : null;
    $threads = (isset($search_result['threads'])) ? $search_result['threads'] : null;
    $profile = $search_profile['profile'];
    $modified = strtotime($search_profile['modified']);
    if ($errors) {
        $cache->remove($cache_id_result, $cache_group_result);
        $cache->remove($cache_id_profile, $cache_group_profile);
    } else {
        // 検索履歴を更新
        if ($_conf['expack.tgrep.recent_num'] > 0) {
            FileCtl::make_datafile($_conf['expack.tgrep.recent_file']);
            $tgrep_recent_list = FileCtl::file_read_lines($_conf['expack.tgrep.recent_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($tgrep_recent_list)) {
                $tgrep_recent_list = array();
            }
            array_unshift($tgrep_recent_list, preg_replace('/[\r\n\t]/', ' ', trim($_GET['Q'])));
            $tgrep_recent_list = array_unique($tgrep_recent_list);
            while (count($tgrep_recent_list) > $_conf['expack.tgrep.recent_num']) {
                array_pop($tgrep_recent_list);
            }
            $tgrep_recent_data = implode("\n", $tgrep_recent_list) . "\n";
            if (FileCtl::file_write_contents($_conf['expack.tgrep.recent_file'], $tgrep_recent_data) === false) {
                p2die('cannot write file.');
            }
        }
    }
} else {
    $errors = null;
    $threads = null;
    $profile = null;
    $modified = '';
}

// }}}
// {{{ 表示用変数を設定

// 基本変数
$htm = array();
$htm['tgrep_url'] = p2h($_conf['expack.tgrep_url']);
$htm['php_self']  = 'tgrepc.php'; //p2h($_SERVER['SCRIPT_NAME']);
$htm['query']     = (isset($_GET['Q'])) ? p2h($_GET['Q']) : '';
$htm['query_en']  = (isset($_GET['Q'])) ? rawurlencode($_GET['Q']) : '';

if (isset($_GET['ib']) && isset($_GET['S']) && isset($_GET['B'])) {
    $htm['category'] = 0;
    $htm['board']   = intval($_GET['ib']);
    $htm['site']    = p2h($_GET['S']);
    $htm['site_en'] = rawurlencode($_GET['S']);
    $htm['bbs']     = p2h($_GET['B']);
    $htm['bbs_en']  = rawurlencode($_GET['B']);
} elseif (isset($_GET['C'])) {
    $htm['category'] = intval($_GET['C']);
    $htm['board']   = 0;
    $htm['site_en'] = $htm['bbs_en'] = $htm['site'] = $htm['bbs'] = '';
} else {
    $htm['category'] = $htm['board'] = 0;
    $htm['site_en'] = $htm['bbs_en'] = $htm['site'] = $htm['bbs'] = '';
}

$htm['skin_q'] = 'skin=' . $skin_en;

if ($profile) {
    $htm['allhits'] = number_format($profile['hits']);
    if ($htm['board'] && isset($profile['boards'][$htm['board']])) {
        $subhits = $profile['boards'][$_GET['ib']]->hits;
        $htm['hits'] = number_format($subhits);
    } elseif ($htm['category'] && isset($profile['categories'][$htm['category']])) {
        $subhits = $profile['categories'][$htm['category']]->hits;
        $htm['hits'] = number_format($subhits);
    } else {
        $subhits = $profile['hits'];
        $htm['hits'] = $htm['allhits'];
    }
} else {
    $subhits = $htm['hits'] = $htm['allhits'] = '';
}

// サーチボックスの属性
if ($_conf['input_type_search']) {
    $htm['search_attr'] = ' type="search" autosave="rep2.expack.search.thread" results="';
    $htm['search_attr'] .= $_conf['expack.tgrep.recent2_num'] . '" placeholder="tGrep"';
} else {
    $htm['search_attr'] = ' type="text"';
}
if (!$_conf['ktai']) {
    $htm['search_attr'] .= ' size="36"';
}
$htm['search_attr'] .= ' maxlength="50" value="' . $htm['query'] . '"';

// スタイルシート
if (!$_conf['ktai'] && !$_conf['iphone']) {
    $htm['message_background'] = "background-color:#ffffcc;";
    if (isset($STYLE['respop_bgcolor']) || isset($STYLE['respop_background'])) {
        $htm['message_background'] = "background:{$STYLE['respop_bgcolor']} {$STYLE['respop_background']};";
    }
    $htm['message_border'] = "border:1px black solid;";
    if (isset($STYLE['respop_b_style']) || isset($STYLE['respop_b_width']) || isset($STYLE['respop_b_color'])) {
        $htm['message_border'] = "border:{$STYLE['respop_b_style']} {$STYLE['respop_b_width']} {$STYLE['respop_b_color']};";
    }
    $htm['message_color'] = '';
    if (isset($STYLE['respop_color'])) {
        $htm['message_color'] = "color:{$STYLE['respop_color']};";
    }
} else {
    $k_body_style = '';
    if ($_conf['mobile.background_color']) {
        $k_body_style .= " background: {$_conf['mobile.background_color']};";
    }
    if ($_conf['mobile.text_color']) {
        $k_body_style .= " color: {$_conf['mobile.text_color']};";
    }
    if ($k_body_style) {
        $k_body_style = 'body {' . $k_body_style . ' }';
    }
    $k_link_style = '';
    if ($_conf['mobile.link_color']) {
        $k_link_style .= " a:link { color: {$_conf['mobile.link_color']}; }";
    }
    if ($_conf['mobile.vlink_color']) {
        $k_link_style .= " a:visited { color: {$_conf['mobile.vlink_color']}; }";
    }
    $k_filter_style = '';
    if ($_conf['mobile.match_color']) {
        $k_filter_style .= " color: {$_conf['mobile.match_color']};";
    }
    /*if (!$_conf['mobile.match_bold']) {
        $k_filter_style .= ' font-weight: normal;';
    }*/
    if ($k_filter_style) {
        $k_filter_style = 'b.filtering {' . $k_filter_style . ' }';
    }
    $htm['mobile_css'] = <<<MOBILE_STYLE
<style type="text/css">
<!--
{$k_body_style}
{$k_link_style}
{$k_filter_style}
-->
</style>
MOBILE_STYLE;
}

// ページャ
if (!$is_ajax && $subhits && $subhits > $limit) {
    $pager_options = array();
    $pager_options = array(
        'mode'          => 'Sliding',
        'totalItems'    => $subhits,
        'perPage'       => $limit,
        'urlVar'        => 'P',
        'extraVars'     => array('_hint' => $_conf['detect_hint']),
        'importQuery'   => false,
        'curPageSpanPre'    => '<b>',
        'curPageSpanPost'   => '</b>',
    );
    $pager_extra_vars = $query_params;
    mb_convert_variables('CP932', 'UTF-8', $pager_extra_vars);
    if (get_magic_quotes_gpc()) {
        $pager_extra_vars = array_map('addslashes', $pager_extra_vars);
    }
    foreach ($pager_extra_vars as $_k => $_v) {
        $pager_options['extraVars'][strtoupper($_k)] = $_v;
    }
    if (!$_conf['ktai']) {
        $pager_options['delta'] = 5;
        $pager_options['separator'] = '|';
        $pager_options['spacesBeforeSeparator'] = 1;
        $pager_options['spacesAfterSeparator']  = 1;
    } else {
        $pager_options['extraVars']['M'] = $modified;
        $pager_options['delta'] = 2;
        $pager_options['separator'] = ' ';
        $pager_options['spacesBeforeSeparator'] = 0;
        $pager_options['spacesAfterSeparator']  = 0;
        $pager_options['altFirst']  = '最初';
        $pager_options['altPrev']   = '前頁';
        $pager_options['altNext']   = '次頁';
        $pager_options['altLast']   = '最後';
        $pager_options['altPage']   = 'p';
    }
    $pager = Pager::factory($pager_options);
    $links = $pager->getLinks();
    $htm['pager'] = implode(' ', array($links['first'], $links['back'], $links['pages'], $links['next'], $links['last']));
} else {
    $htm['pager'] = '';
}

// }}}
// {{{ 表示

if (empty($_GET['M'])) {
    P2Util::header_nocache();
}

if ($is_ajax) {
    require_once P2_LIB_DIR . '/menu_iphone.inc.php';
    ob_start();
    include P2EX_LIB_DIR . '/tgrep/view_x.inc.php';
    $content = mb_convert_encoding(ob_get_clean(), 'UTF-8', 'CP932');
    if (!headers_sent()) {
        header('Content-Type: application/xml; charset=UTF-8');
        //header('Content-Type: text/plain; charset=UTF-8');
        //header('Content-Length: ' . strlen($content));
    }
    echo $content;
} elseif ($_conf['ktai']) {
    include P2EX_LIB_DIR . '/tgrep/view_k.inc.php';
} else {
    include P2EX_LIB_DIR . '/tgrep/view.inc.php';
}
exit;

// }}}
// {{{ tgrep_search()

function tgrep_search($query)
{
    global $_conf;
    $client = new HTTP_Client();
    $client->setDefaultHeader('User-Agent', 'p2-tgrep-client');
    $code = $client->get($_conf['expack.tgrep_url'] . '?' . $query);
    if (PEAR::isError($code)) {
        p2die($code->getMessage());
    } elseif ($code != 200) {
        p2die("HTTP Error - {$code}");
    }
    $response = $client->currentResponse();
    $result = unserialize($response['body']);
    if (!$result) {
        p2die('Error: 検索結果の展開に失敗しました。');
    }
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
