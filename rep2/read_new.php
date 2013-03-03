<?php
/**
 * rep2 - スレッド表示スクリプト - 新着まとめ読み
 * フレーム分割画面、右下部分
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

// +Wiki
require_once P2_LIB_DIR . '/wiki/read.inc.php';

//==================================================================
// 変数
//==================================================================
if (isset($_conf['rnum_all_range']) and $_conf['rnum_all_range'] > 0) {
    $GLOBALS['rnum_all_range'] = $_conf['rnum_all_range'];
}

$sb_view = 'shinchaku';
$newtime = date('gis');

$online_num = 0;
$newthre_num = 0;

//=================================================
// 板の指定
//=================================================
if (isset($_GET['host'])) { $host = $_GET['host']; }
if (isset($_POST['host'])) { $host = $_POST['host']; }
if (isset($_GET['bbs'])) { $bbs = $_GET['bbs']; }
if (isset($_POST['bbs'])) { $bbs = $_POST['bbs']; }
if (isset($_GET['spmode'])) { $spmode = $_GET['spmode']; }
if (isset($_POST['spmode'])) { $spmode = $_POST['spmode']; }

if ((!isset($host) || !isset($bbs)) && !isset($spmode)) {
    p2die('必要な引数が指定されていません');
}

//=================================================
// あぼーん&NGワード設定読み込み
//=================================================
$GLOBALS['ngaborns'] = NgAbornCtl::loadNgAborns();

//====================================================================
// メイン
//====================================================================

$aThreadList = new ThreadList();

// 板とモードのセット===================================
$ta_keys = array();
if ($spmode) {
    if ($spmode == 'taborn' or $spmode == 'soko') {
        $aThreadList->setIta($host, $bbs, P2Util::getItaName($host, $bbs));
    }
    $aThreadList->setSpMode($spmode);
} else {
    $aThreadList->setIta($host, $bbs, P2Util::getItaName($host, $bbs));

    // スレッドあぼーんリスト読込
    $taborn_file = $aThreadList->getIdxDir() . 'p2_threads_aborn.idx';
    if ($tabornlines = FileCtl::file_read_lines($taborn_file, FILE_IGNORE_NEW_LINES)) {
        $ta_num = sizeof($tabornlines);
        foreach ($tabornlines as $l) {
            $tarray = explode('<>', $l);
            $ta_keys[ $tarray[1] ] = true;
        }
    }
}

// ソースリスト読込
if ($spmode == 'merge_favita') {
    if ($_conf['expack.misc.multi_favs'] && !empty($_conf['m_favita_set'])) {
        $merged_faivta_read_idx = $_conf['pref_dir'] . '/p2_favita' . $_conf['m_favita_set'] . '_read.idx';
    } else {
        $merged_faivta_read_idx = $_conf['pref_dir'] . '/p2_favita_read.idx';
    }
    $lines = FileCtl::file_read_lines($merged_faivta_read_idx);
    if (is_array($lines)) {
        $have_merged_faivta_read_idx = true;
    } else {
        $have_merged_faivta_read_idx = false;
        $lines = $aThreadList->readList();
    }
} else {
    $lines = $aThreadList->readList();
}

// ページヘッダ表示 ===================================
$ptitle_hd = p2h($aThreadList->ptitle);
$ptitle_ht = "{$ptitle_hd} の 新着まとめ読み";
$matomeCache = new MatomeCache($ptitle_hd, $_conf['matome_cache_max']);
ob_start();

if ($aThreadList->spmode) {
    $sb_ht = <<<EOP
        <a href="{$_conf['subject_php']}?host={$aThreadList->host}&amp;bbs={$aThreadList->bbs}&amp;spmode={$aThreadList->spmode}" target="subject">{$ptitle_hd}</a>
EOP;
} else {
    $sb_ht = <<<EOP
        <a href="{$_conf['subject_php']}?host={$aThreadList->host}&amp;bbs={$aThreadList->bbs}" target="subject">{$ptitle_hd}</a>
EOP;
}

// require_once P2_LIB_DIR . '/read_header.inc.php';

echo $_conf['doctype'];
echo <<<EOHEADER
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>{$ptitle_ht}</title>
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=read&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/respopup.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/motolspopup.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/ngabornctl.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/setfavjs.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/delelog.js?{$_conf['p2_version_id']}"></script>\n
EOHEADER;

if ($_conf['iframe_popup_type'] == 1) {
    echo <<<EOP
    <script type="text/javascript" src="./js/yui-ext/yui.js"></script>
    <script type="text/javascript" src="./js/yui-ext/yui-ext-nogrid.js"></script>
    <link rel="stylesheet" type="text/css" href="./js/yui-ext/resources/css/resizable.css">
    <script type="text/javascript" src="js/htmlpopup_resizable.js?{$_conf['p2_version_id']}"></script>
EOP;
} else {
    echo <<<EOP
    <script type="text/javascript" src="js/htmlpopup.js?{$_conf['p2_version_id']}"></script>
EOP;
}

if ($_conf['link_youtube'] == 2 || $_conf['link_niconico'] == 2) {
    echo <<<EOP
    <script type="text/javascript" src="js/preview_video.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}
if ($_conf['expack.am.enabled']) {
    echo <<<EOP
    <script type="text/javascript" src="js/asciiart.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}
/*if ($_conf['expack.misc.async_respop']) {
    echo <<<EOP
    <script type="text/javascript" src="js/async.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}*/
if ($_conf['expack.spm.enabled']) {
    $info_popup_width = 0;
    $info_popup_height = 0;
    $post_popup_width = 0;
    $post_popup_height = 0;
    sscanf($STYLE['info_pop_size'], '%u,%u', $info_popup_width, $info_popup_height);
    sscanf($STYLE['post_pop_size'], '%u,%u', $post_popup_width, $post_popup_height);
    echo <<<EOP
    <script type="text/javascript">
    // <![CDATA[
    var info_popup_width = {$info_popup_width};
    var info_popup_height = {$info_popup_height};
    var post_popup_width = {$post_popup_width};
    var post_popup_height = {$post_popup_height};
    // ]]>
    </script>
    <script type="text/javascript" src="js/invite.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/smartpopup.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}
if ($_conf['expack.ic2.enabled']) {
    echo <<<EOP
    <script type="text/javascript" src="js/json2.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/loadthumb.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/ic2_getinfo.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/ic2_popinfo.js?{$_conf['p2_version_id']}"></script>
    <link rel="stylesheet" type="text/css" href="css/ic2_popinfo.css?{$_conf['p2_version_id']}">\n
EOP;
}
if ($_conf['coloredid.enable'] > 0 && $_conf['coloredid.click'] > 0) {
    echo <<<EOP
    <script type="text/javascript" src="js/colorLib.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/coloredId.js?{$_conf['p2_version_id']}"></script>
EOP;
}

$onload_script = '';

if ($_conf['iframe_popup_type'] == 1) {
    $fade = empty($_GET['fade']) ? 'false' : 'true';
    $onload_script .= "gFade = {$fade};";
    $bodyadd = ' onclick="hideHtmlPopUp(event);"';
EOP;
}

if ($_conf['backlink_coloring_track']) {
    $onload_script .= '(function() { for(var i=0; i<rescolObjs.length; i++) {rescolObjs[i].setUp(); }})();';
    echo <<<EOP
    <script type="text/javascript" src="js/backlink_color.js?{$_conf['p2_version_id']}"></script>
EOP;
}

if ($_conf['expack.ic2.enabled'] && $_conf['expack.ic2.thread_imagelink'] && $_conf['expack.ic2.thread_imagecount']) {
    echo <<<EOP
    <script type="text/javascript" src="js/ic2_getcount.js?{$_conf['p2_version_id']}"></script>
EOP;
    $onload_script .= <<<EOHEADER
window.setWindowOnLoad(ic2_setcount_new);
EOHEADER;
}

if ($_conf['wiki.idsearch.spm.mimizun.enabled'] ||
    $_conf['wiki.idsearch.spm.hissi.enabled'] ||
    $_conf['wiki.idsearch.spm.stalker.enabled']) {
    echo <<<EOP
    <script type="text/javascript" src="js/idtool.js?{$_conf['p2_version_id']}"></script>
EOP;
}

// pageLoaded()が他のJavaScriptでも定義されたロード時のイベントハンドラとかぶらないようにする。
// 古いブラウザでDOMContentLoadedと同等のタイミングにはこだわらない。
// rep2はフレーム前提なのでjQuery.bindReady()のような技は使えない（ぽい）。
echo <<<EOHEADER
    <script type="text/javascript">
    //<![CDATA[
    gIsPageLoaded = false;

    function pageLoaded()
    {
        gIsPageLoaded = true;
        {$onload_script}
        setWinTitle();
    }

    (function(){
        if (typeof window.p2BindReady == 'undefined') {
            window.setTimeout(arguments.callee, 100);
        } else {
            window.p2BindReady(pageLoaded, 'js/defer/pageLoaded.js');
        }
    })();
    //]]>
    </script>\n
EOHEADER;

if (!empty($_SESSION['use_narrow_toolbars'])) {
    echo <<<EOP
    <link rel="stylesheet" type="text/css" href="css.php?css=narrow_toolbar&amp;skin={$skin_en}">\n
EOP;
}

echo <<<EOP
</head>
<body{$bodyadd}><div id="popUpContainer"></div>\n
EOP;

P2Util::printInfoHtml();

//echo $ptitle_ht."<br>";

//==============================================================
// それぞれの行解析
//==============================================================

$linesize = sizeof($lines);
$subject_txts = array();

for ($x = 0; $x < $linesize ; $x++) {

    if (isset($GLOBALS['rnum_all_range']) and $GLOBALS['rnum_all_range'] <= 0) {
        break;
    }

    $l = $lines[$x];
    $aThread = new ThreadRead();

    $aThread->torder = $x + 1;

    // データ読み込み
    // spmodeなら
    if ($aThreadList->spmode) {
        switch ($aThreadList->spmode) {
        case "recent": // 履歴
            $aThread->getThreadInfoFromExtIdxLine($l);
            break;
        case "res_hist": // 書き込み履歴
            $aThread->getThreadInfoFromExtIdxLine($l);
            break;
        case "fav": // お気に
            $aThread->getThreadInfoFromExtIdxLine($l);
            break;
        case "taborn": // スレッドあぼーん
            $aThread->getThreadInfoFromExtIdxLine($l);
            $aThread->host = $aThreadList->host;
            $aThread->bbs = $aThreadList->bbs;
            break;
        case "palace": // スレの殿堂
            $aThread->getThreadInfoFromExtIdxLine($l);
            break;
        case "merge_favita": // お気に板をマージ
            if ($have_merged_faivta_read_idx) {
                $aThread->getThreadInfoFromExtIdxLine($l);
            } else {
                $aThread->key = $l['key'];
                $aThread->setTtitle($l['ttitle']);
                $aThread->rescount = $l['rescount'];
                $aThread->host = $l['host'];
                $aThread->bbs = $l['bbs'];
                $aThread->torder = $l['torder'];
            }
            break;
        }
    // subject (not spmode)の場合
    } else {
        $aThread->getThreadInfoFromSubjectTxtLine($l);
        $aThread->host = $aThreadList->host;
        $aThread->bbs = $aThreadList->bbs;
    }

    // hostもbbsも不明ならスキップ
    if (!($aThread->host && $aThread->bbs)) {
        unset($aThread);
        continue;
    }

    $subject_id = $aThread->host . '/' . $aThread->bbs;

    $aThread->setThreadPathInfo($aThread->host, $aThread->bbs, $aThread->key);

    // 既得スレッドデータをidxから取得
    $aThread->getThreadInfoFromIdx();

    // 新着のみ(for subject) =========================================
    if (!$aThreadList->spmode && $sb_view == 'shinchaku' && empty($_GET['word'])) {
        if ($aThread->unum < 1) {
            unset($aThread);
            continue;
        }
    }

    // スレッドあぼーんチェック =====================================
    if ($aThreadList->spmode != 'taborn' && !empty($ta_keys[$aThread->key])) {
            unset($ta_keys[$aThread->key]);
            continue; // あぼーんスレはスキップ
    }

    //  spmode(殿堂入りを除く)なら ====================================
    if ($aThreadList->spmode && $sb_view != "edit") {

        // subject.txt が未DLなら落としてデータを配列に格納
        if (empty($subject_txts[$subject_id])) {
            $aSubjectTxt = new SubjectTxt($aThread->host, $aThread->bbs);

            $subject_txts[$subject_id] = $aSubjectTxt->subject_lines;
        }

        // スレ情報取得 =============================
        if (!empty($subject_txts[$subject_id])) {
            $thread_key = (string)$aThread->key;
            $thread_key_len = strlen($thread_key);
            foreach ($subject_txts[$subject_id] as $l) {
                if (strncmp($l, $thread_key, $thread_key_len) == 0) {
                    $aThread->getThreadInfoFromSubjectTxtLine($l); // subject.txt からスレ情報取得
                    break;
                }
            }
        }

        // 新着のみ(for spmode) ===============================
        if ($sb_view == 'shinchaku' && empty($_GET['word'])) {
            if ($aThread->unum < 1) {
                unset($aThread);
                continue;
            }
        }
    }

    if ($aThread->isonline) { $online_num++; } // 生存数set

    P2Util::printInfoHtml();

    $matomeCache->concat(ob_get_flush());
    flush();
    ob_start();

    if (($aThread->readnum < 1) || $aThread->unum) {
        if ($_conf['expack.ic2.enabled'] && $_conf['expack.ic2.thread_imagelink'] && $_conf['expack.ic2.thread_imagecount']) {
            $threads[] = array($aThread->ttitle, $aThread->keydat);
        }
        readNew($aThread);
        $matomeCache->addReadThread($aThread);
    } elseif ($aThread->diedat) {
        echo $aThread->getdat_error_msg_ht;
        echo "<hr>\n";
    }

    $matomeCache->concat(ob_get_flush());
    flush();
    ob_start();

    // リストに追加 ========================================
    // $aThreadList->addThread($aThread);
    $aThreadList->num++;
    unset($aThread);
}

// IC2画像数をセットするJS
if ($_conf['expack.ic2.enabled'] && $_conf['expack.ic2.thread_imagelink'] && $_conf['expack.ic2.thread_imagecount']) {
    $ic2_count_js = '';
    if (is_array($threads)) {
        foreach ($threads as $_ttitle) {
            list($_ttl, $_kdat) = $_ttitle;
            $spanid = 'ic2_count_' . sprintf('%u', crc32($_kdat));
            $ic2_count_js .= "ic2_setcount('" . rawurlencode($_ttl) .
                "', '{$spanid}');";
        }
    }
    echo <<<EOP
<script type="text/javascript">
function ic2_setcount_new() {
{$ic2_count_js}
return;
}
</script>
EOP;
}

// $aThread = new ThreadRead();

//======================================================================
//  スレッドの新着部分を読み込んで表示する
//======================================================================
function readNew($aThread)
{
    global $_conf, $newthre_num, $STYLE;
    global $word;
    static $favlist_titles = null;

    if ($_conf['expack.misc.multi_favs'] && is_null($favlist_titles)) {
        $favlist_titles = FavSetManager::getFavSetTitles('m_favlist_set');
        if (empty($favlist_titles)) {
            $favlist_titles = array();
        }
        if (!isset($favlist_titles[0]) || $favlist_titles[0] == '') {
            $favlist_titles[0] = 'お気にスレ';
        }
        for ($i = 1; $i <= $_conf['expack.misc.favset_num']; $i++) {
            if (!isset($favlist_titles[$i]) || $favlist_titles[$i] == '') {
                $favlist_titles[$i] = 'お気にスレ' . $i;
            }
        }
    }

    $newthre_num++;

    //==========================================================
    //  idxの読み込み
    //==========================================================

    // hostを分解してidxファイルのパスを求める
    $aThread->setThreadPathInfo($aThread->host, $aThread->bbs, $aThread->key);

    // FileCtl::mkdirFor($aThread->keyidx); // 板ディレクトリが無ければ作る // この操作はおそらく不要

    $aThread->itaj = P2Util::getItaName($aThread->host, $aThread->bbs);
    if (!$aThread->itaj) { $aThread->itaj = $aThread->bbs; }

    // idxファイルがあれば読み込む
    if ($lines = FileCtl::file_read_lines($aThread->keyidx, FILE_IGNORE_NEW_LINES)) {
        $data = explode('<>', $lines[0]);
    } else {
        $data = array_fill(0, 12, '');
    }
    $aThread->getThreadInfoFromIdx();

    //==================================================================
    // DATのダウンロード
    //==================================================================
    if (!($word and file_exists($aThread->keydat))) {
        $aThread->downloadDat();
    }

    // DATを読み込み
    $aThread->readDat();
    $aThread->setTitleFromLocal(); // ローカルからタイトルを取得して設定

    //===========================================================
    // 表示レス番の範囲を設定
    //===========================================================
    // 取得済みなら
    if ($aThread->isKitoku()) {
        $from_num = $aThread->readnum +1 - $_conf['respointer'] - $_conf['before_respointer_new'];
        if ($from_num > $aThread->rescount) {
            $from_num = $aThread->rescount - $_conf['respointer'] - $_conf['before_respointer_new'];
        }
        if ($from_num < 1) {
            $from_num = 1;
        }

        //if (!$aThread->ls) {
            $aThread->ls = "$from_num-";
        //}
    }

    $aThread->lsToPoint();

    //==================================================================
    // ヘッダ 表示
    //==================================================================
    $motothre_url = $aThread->getMotoThread(false, '');

    $ttitle_en = UrlSafeBase64::encode($aThread->ttitle);
    $ttitle_en_q = '&amp;ttitle_en=' . $ttitle_en;
    $bbs_q = '&amp;bbs=' . $aThread->bbs;
    $key_q = '&amp;key=' . $aThread->key;
    $host_bbs_key_q = 'host=' . $aThread->host . $bbs_q . $key_q;
    $popup_q = '&amp;popup=1';

    // require_once P2_LIB_DIR . '/read_header.inc.php';

    $prev_thre_num = $newthre_num - 1;
    $next_thre_num = $newthre_num + 1;
    if ($prev_thre_num != 0) {
        $prev_thre_ht = "<a href=\"#ntt{$prev_thre_num}\">▲</a>";
    } else {
        $prev_thre_ht = '';
    }
    $next_thre_ht = "<a id=\"ntta{$next_thre_num}\" href=\"#ntt{$next_thre_num}\">▼</a>";

    P2Util::printInfoHtml();

    // ヘッダ部分HTML
    $read_header_ht = <<<EOP
<table id="ntt{$newthre_num}" class="toolbar">
    <tr>
        <td class="lblock"><h3 class="thread_title">{$aThread->ttitle_hd}</h3></td>
        <td class="rblock">{$prev_thre_ht} {$next_thre_ht}</td>
    </tr>
</table>\n
EOP;

    //==================================================================
    // ローカルDatを読み込んでHTML表示
    //==================================================================
    $aThread->resrange['nofirst'] = true;
    $GLOBALS['newres_to_show_flag'] = false;
    if ($aThread->rescount) {
        $aShowThread = new ShowThreadPc($aThread, true);

        if ($_conf['expack.spm.enabled']) {
            $read_header_ht .= $aShowThread->getSpmObjJs();
        }

        $res1 = $aShowThread->quoteOne();
        $read_cont_ht = $res1['q'];
        $read_cont_ht .= $aShowThread->getDatToHtml();

        // レス追跡カラー
        if ($_conf['backlink_coloring_track']) {
            $read_cont_ht .= $aShowThread->getResColorJs();
        }

        // IDカラーリング
        if ($_conf['coloredid.enable'] > 0 && $_conf['coloredid.click'] > 0) {
            $read_header_ht .= $aShowThread->getIdColorJs();
        }

        // 外部ツール
        $pluswiki_js = '';

        if ($_conf['wiki.idsearch.spm.mimizun.enabled']) {
            if (!class_exists('Mimizun', false)) {
                require P2_PLUGIN_DIR . '/mimizun/Mimizun.php';
            }
            $mimizun = new Mimizun();
            $mimizun->host = $aThread->host;
            $mimizun->bbs  = $aThread->bbs;
            if ($mimizun->isEnabled()) {
                $pluswiki_js .= "WikiTools.addMimizun({$aShowThread->spmObjName});";
            }
        }

        if ($_conf['wiki.idsearch.spm.hissi.enabled']) {
            if (!class_exists('Hissi', false)) {
                require P2_PLUGIN_DIR . '/hissi/Hissi.php';
            }
            $hissi = new Hissi();
            $hissi->host = $aThread->host;
            $hissi->bbs  = $aThread->bbs;
            if ($hissi->isEnabled()) {
                $pluswiki_js .= "WikiTools.addHissi({$aShowThread->spmObjName});";
            }
        }

        if ($_conf['wiki.idsearch.spm.stalker.enabled']) {
            if (!class_exists('Stalker', false)) {
                require P2_PLUGIN_DIR . '/stalker/Stalker.php';
            }
            $stalker = new Stalker();
            $stalker->host = $aThread->host;
            $stalker->bbs  = $aThread->bbs;
            if ($stalker->isEnabled()) {
                $pluswiki_js .= "WikiTools.addStalker({$aShowThread->spmObjName});";
            }
        }

        if ($pluswiki_js !== '') {
            $read_header_ht .= <<<EOP
<script type="text/javascript">
//<![CDATA[
{$pluswiki_js}
//]]>
</script>
EOP;
        }

        unset($aShowThread);
    }

    //==================================================================
    // フッタ 表示
    //==================================================================
    // $read_footer_navi_new  続きを読む 新着レスの表示
    $newtime = date("gis");  // リンクをクリックしても再読込しない仕様に対抗するダミークエリー

    $info_st = '情報';
    $delete_st = '削除';
    $prev_st = '前';
    $next_st = '次';
    $dores_st = '書込';

    $read_footer_navi_new = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->rescount}-&amp;nt=$newtime#r{$aThread->rescount}\">新着レスの表示</a>";

    if (!empty($_conf['disable_res'])) {
        $dores_ht = <<<EOP
          <a href="{$motothre_url}" target="_blank">{$dores_st}</a>
EOP;
    } else {
        $dores_ht = <<<EOP
        <a href="post_form.php?{$host_bbs_key_q}&amp;rescount={$aThread->rescount}{$ttitle_en_q}" target='_self' onclick="return OpenSubWin('post_form.php?{$host_bbs_key_q}&amp;rescount={$aThread->rescount}{$ttitle_en_q}{$popup_q}&amp;from_read_new=1',{$STYLE['post_pop_size']},1,0)">{$dores_st}</a>
EOP;
    }

    // ツールバー部分HTML =======

    // お気にマーク設定
    $itaj_hd = p2h($aThread->itaj);
    $similar_q = '&amp;itaj_en=' . UrlSafeBase64::encode($aThread->itaj)
               . '&amp;method=similar&amp;word=' . rawurlencode($aThread->ttitle_hc);

    if ($_conf['expack.misc.multi_favs']) {
        $toolbar_setfav_ht = 'お気に[';
        $favdo = (!empty($aThread->favs[0])) ? 0 : 1;
        $favdo_q = '&amp;setfav=' . $favdo;
        $favmark = $favdo ? '+' : '★';
        $favtitle = $favlist_titles[0] . ($favdo ? 'に追加' : 'から外す');
        $setnum_q = '&amp;setnum=0';
        $toolbar_setfav_ht .= <<<EOP
<span class="favdo set0"><a href="info.php?{$host_bbs_key_q}{$ttitle_en_q}{$favdo_q}{$setnum_q}" target="info" onclick="return setFavJs('{$host_bbs_key_q}{$ttitle_en_q}', '{$favdo}', {$STYLE['info_pop_size']}, 'read_new', this, '0');" title="{$favtitle}">{$favmark}</a></span>
EOP;
        for ($i = 1; $i <= $_conf['expack.misc.favset_num']; $i++) {
            $favdo = (!empty($aThread->favs[$i])) ? 0 : 1;
            $favdo_q = '&amp;setfav=' . $favdo;
            $favmark = $favdo ? $i : '★';
            $favtitle = $favlist_titles[$i] . ($favdo ? 'に追加' : 'から外す');
            $setnum_q = '&amp;setnum=' . $i;
            $toolbar_setfav_ht .= <<<EOP
|<span class="favdo set{$i}"><a href="info.php?{$host_bbs_key_q}{$ttitle_en_q}{$favdo_q}{$setnum_q}" target="info" onclick="return setFavJs('{$host_bbs_key_q}{$ttitle_en_q}', '{$favdo}', {$STYLE['info_pop_size']}, 'read_new', this, '{$i}');" title="{$favtitle}">{$favmark}</a></span>
EOP;
        }
        $toolbar_setfav_ht .= ']';
    } else {
        $favdo = (!empty($aThread->fav)) ? 0 : 1;
        $favdo_q = '&amp;setfav=' . $favdo;
        $favmark = $favdo ? '+' : '★';
        $favtitle = $favdo ? 'お気にスレに追加' : 'お気にスレから外す';
        $toolbar_setfav_ht = <<<EOP
<span class="favdo"><a href="info.php?{$host_bbs_key_q}{$ttitle_en_q}{$favdo_q}" target="info" onclick="return setFavJs('{$host_bbs_key_q}{$ttitle_en_q}', '{$favdo}', {$STYLE['info_pop_size']}, 'read_new', this, '0');" title="{$favtitle}">お気に{$favmark}</a></span>
EOP;
    }

    $toolbar_right_ht = <<<EOTOOLBAR
            <a href="{$_conf['subject_php']}?{$host_bbs_key_q}" target="subject" title="板を開く">{$itaj_hd}</a>
            <a href="info.php?{$host_bbs_key_q}{$ttitle_en_q}" target="info" onclick="return OpenSubWin('info.php?{$host_bbs_key_q}{$ttitle_en_q}{$popup_q}',{$STYLE['info_pop_size']},1,0)" title="スレッド情報を表示">{$info_st}</a>
            {$toolbar_setfav_ht}
            <span><a href="info.php?{$host_bbs_key_q}{$ttitle_en_q}&amp;dele=true" target="info" onclick="return deleLog('{$host_bbs_key_q}{$ttitle_en_q}', {$STYLE['info_pop_size']}, 'read_new', this);" title="ログを削除する">{$delete_st}</a></span>
<!--            <a href="info.php?{$host_bbs_key_q}{$ttitle_en_q}&amp;taborn=2" target="info" onclick="return OpenSubWin('info.php?{$host_bbs_key_q}{$ttitle_en_q}&amp;popup=2&amp;taborn=2',{$STYLE['info_pop_size']},0,0)" title="スレッドのあぼーん状態をトグルする">あぼん</a> -->
            <a href="{$motothre_url}" title="板サーバ上のオリジナルスレを表示" onmouseover="showMotoLsPopUp(event, this)" onmouseout="hideMotoLsPopUp()">元スレ</a>
            <a href="{$_conf['subject_php']}?{$host_bbs_key_q}{$similar_q}" target="subject" title="タイトルが似ているスレッドを検索">似スレ</a>
EOTOOLBAR;

    // レスのすばやさ
    $spd_ht = "";
    if ($spd_st = $aThread->getTimePerRes() and $spd_st != "-") {
        $spd_ht = '<span class="spd" title="すばやさ＝時間/レス">'."" . $spd_st."".'</span>';
    }

    // datサイズ
    if (file_exists($aThread->keydat) && $dsize_ht = filesize($aThread->keydat)) {
        $dsize_ht = sprintf('<span class="spd" title="%s">%01.1fKB</span> |', 'datサイズ', $dsize_ht / 1024);
    } else {
        $dsize_ht = '';
    }

    // IC2リンク、件数
    if ($_conf['expack.ic2.enabled'] && $_conf['expack.ic2.thread_imagelink']) {
        $spanid = 'ic2_count_' . sprintf('%u', crc32($aThread->keydat));
        $ic2navi = '<a href="iv2.php?field=memo&amp;keyword=' .
            rawurlencode($aThread->ttitle) .
            '" target="_blank">キャッシュ画像' .
            ($_conf['expack.ic2.thread_imagecount'] ?
                '<span id="ic2_count_' .
                sprintf('%u', crc32($aThread->keydat)) .
                '"></span>' : '') .
            '</a> ';
    }

    // フッタ部分HTML
    $read_footer_ht = <<<EOP
<table class="toolbar">
    <tr>
        <td class="lblock">{$res1['body']} | <a href="{$_conf['read_php']}?{$host_bbs_key_q}&amp;offline=1&amp;rescount={$aThread->rescount}#r{$aThread->rescount}">{$aThread->ttitle_hd}</a> | {$dores_ht} {$dsize_ht} {$spd_ht}</td>
        <td class="rblock">{$ic2navi}{$toolbar_right_ht}</td>
        <td class="rblock"><a href="#ntt{$newthre_num}">▲</a></td>
    </tr>
</table>\n
EOP;

    // 透明あぼーんで表示がない場合はスキップ
    if ($GLOBALS['newres_to_show_flag']) {
        echo '<div style="width:100%;">'."\n"; // ほぼIE ActiveXのGray()のためだけに囲ってある
        echo $read_header_ht;
        echo $read_cont_ht;
        echo $read_footer_ht;
        echo '</div>'."\n\n";
        echo '<hr>'."\n\n";
    }

    //==================================================================
    // key.idx の値設定
    //==================================================================
    if ($aThread->rescount) {

        $aThread->readnum = min($aThread->rescount, max(0, $data[5], $aThread->resrange['to']));

        $newline = $aThread->readnum + 1; // $newlineは廃止予定だが、旧互換用に念のため

        $sar = array($aThread->ttitle, $aThread->key, $data[2], $aThread->rescount, $aThread->modified,
                    $aThread->readnum, $data[6], $data[7], $data[8], $newline,
                    $data[10], $data[11], $aThread->datochiok);
        P2Util::recKeyIdx($aThread->keyidx, $sar); // key.idxに記録
    }
}

//==================================================================
// ページフッタ表示
//==================================================================
$newthre_num++;

if (!$aThreadList->num) {
    echo "新着レスはないぽ";
    echo "<hr>";
}

$shinchaku_matome_url = "{$_conf['read_new_php']}?host={$aThreadList->host}&amp;bbs={$aThreadList->bbs}&amp;spmode={$aThreadList->spmode}&amp;nt={$newtime}";

if ($aThreadList->spmode == 'merge_favita') {
    $shinchaku_matome_url .= $_conf['m_favita_set_at_a'];
}

if (!isset($GLOBALS['rnum_all_range']) or $GLOBALS['rnum_all_range'] > 0 or !empty($GLOBALS['limit_to_eq_to'])) {
    if (!empty($GLOBALS['limit_to_eq_to'])) {
        $str = '新着まとめ読みの更新/続き';
    } else {
        $str = '新着まとめ読みを更新';
    }
} else {
    $str = '新着まとめ読みの続き';
    $shinchaku_matome_url .= '&amp;norefresh=1';
}

echo <<<EOP
<div id="ntt{$newthre_num}" align="center">{$sb_ht} の <a href="{$shinchaku_matome_url}">{$str}</a></div>\n
EOP;

if ($_conf['expack.ic2.enabled']) {
    echo "<script type=\"text/javascript\" src=\"js/ic2_popinfo.js\"></script>";
    include P2EX_LIB_DIR . '/ImageCache2/templates/info.tpl.html';
}

echo '</body></html>';

$matomeCache->concat(ob_get_flush());

// NGあぼーんを記録
NgAbornCtl::saveNgAborns();

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
