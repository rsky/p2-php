<?php
/**
 * rep2 - スレッド表示 -  ヘッダ部分 -  携帯用 for read.php
 */

// 変数 =====================================
$diedat_msg = '';

$info_st = '情';
$delete_st = '削';
$prev_st = '前';
$next_st = '次';
$shinchaku_st = '新着';
$moto_thre_st = '元';
$siml_thre_st = '似';
$latest_st = '新';
$dores_st = '書';
$find_st = '索';

$motothre_url = $aThread->getMotoThread(false, '1-10');
$ttitle_en = UrlSafeBase64::encode($aThread->ttitle);
$ttitle_en_q = '&amp;ttitle_en=' . $ttitle_en;
$bbs_q = '&amp;bbs=' . $aThread->bbs;
$key_q = '&amp;key=' . $aThread->key;
$host_bbs_key_q = 'host=' . $aThread->host . $bbs_q . $key_q;
$offline_q = '&amp;offline=1';

$hd['word'] = ResFilter::getWord('p2h');
$do_filtering = ($hd['word'] === null) ? false : true;

//=================================================================
// ヘッダ
//=================================================================

// お気にマーク設定
$favmark = ($aThread->fav) ? '<span class="fav">★</span>' : '<span class="fav">+</span>';
$favdo = ($aThread->fav) ? 0 : 1;

// レスナビ設定 =====================================================

$rnum_range = $_conf['mobile.rnum_range'];
$latest_show_res_num = $_conf['mobile.rnum_range']; // 最新XX

$read_navi_range = '';
$read_navi_previous = '';
$read_navi_previous_btm = '';
$read_navi_next = '';
$read_navi_next_btm = '';
$read_footer_navi_new = '';
$read_footer_navi_new_btm = '';
$read_navi_latest = '';
$read_navi_latest_btm = '';
$read_navi_filter = '';
$read_navi_filter_btm = '';

$pointer_header_at = ' id="header" name="header"';

//----------------------------------------------
// $htm['read_navi_range'] -- 1- 101- 201-

$htm['read_navi_range'] = "<a{$pointer_header_at} href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls=1-{$rnum_range}{$offline_q}{$_conf['k_at_a']}\"{$_conf['k_accesskey_at'][1]}>1-</a>\t";


//----------------------------------------------
// $read_navi_previous -- 前100
$before_rnum = $aThread->resrange['start'] - $rnum_range;
if ($before_rnum < 1) { $before_rnum = 1; }
if ($aThread->resrange['start'] == 1) {
    $read_navi_previous_isInvisible = true;
} else {
    $read_navi_previous_isInvisible = false;
}
//if ($before_rnum != 1) {
//    $read_navi_previous_anchor = "#r{$before_rnum}";
//} else {
    $read_navi_previous_anchor = '';
//}

if (!$read_navi_previous_isInvisible) {
    $read_navi_previous = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$before_rnum}-{$aThread->resrange['start']}n{$offline_q}{$_conf['k_at_a']}{$read_navi_previous_anchor}\">{$prev_st}</a>";
    $read_navi_previous_btm = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$before_rnum}-{$aThread->resrange['start']}n{$offline_q}{$_conf['k_at_a']}{$read_navi_previous_anchor}\"{$_conf['k_accesskey_at']['prev']}>{$_conf['k_accesskey_st']['prev']}{$prev_st}</a>";
}

//----------------------------------------------
// $read_navi_next -- 次100
if ($do_filtering || !empty($_GET['one'])) {
    $read_navi_next_isInvisible = false;
} elseif ($aThread->resrange['to'] >= $aThread->rescount) {
    $aThread->resrange['to'] = $aThread->rescount;
    //$read_navi_next_anchor = "#r{$aThread->rescount}";
    $read_navi_next_isInvisible = true;
} else {
    $read_navi_next_isInvisible = false;
    // $read_navi_next_anchor = "#r{$aThread->resrange['to']}";
}
if ($aThread->resrange['to'] == $aThread->rescount) {
    $read_navi_next_anchor = "#r{$aThread->rescount}";
} else {
    $read_navi_next_anchor = '';
}
$after_rnum = $aThread->resrange['to'] + $rnum_range;

if (!$read_navi_next_isInvisible) {
    $read_navi_next = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->resrange['to']}-{$after_rnum}n{$offline_q}&amp;nt={$newtime}{$_conf['k_at_a']}{$read_navi_next_anchor}\">{$next_st}</a>";
    $read_navi_next_btm = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->resrange['to']}-{$after_rnum}n{$offline_q}&amp;nt={$newtime}{$_conf['k_at_a']}{$read_navi_next_anchor}\"{$_conf['k_accesskey_at']['next']}>{$_conf['k_accesskey_st']['next']}{$next_st}</a>";
}

//----------------------------------------------
// $read_footer_navi_new  続きを読む 新着レスの表示

if ($aThread->resrange['to'] == $aThread->rescount) {
    $read_footer_navi_new = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->rescount}-n&amp;nt={$newtime}{$_conf['k_at_a']}#r{$aThread->rescount}\">{$shinchaku_st}</a>";
    $read_footer_navi_new_btm = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->rescount}-n&amp;nt={$newtime}{$_conf['k_at_a']}#r{$aThread->rescount}\"{$_conf['k_accesskey_at']['next']}>{$_conf['k_accesskey_st']['next']}{$shinchaku_st}</a>";
}

if (!$read_navi_next_isInvisible) {
    $read_navi_latest = <<<EOP
<a href="{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls=l{$latest_show_res_num}{$_conf['k_at_a']}">{$latest_st}{$latest_show_res_num}</a>
EOP;
    $time = time();
    $read_navi_latest_btm = <<<EOP
<a href="{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls=l{$latest_show_res_num}&amp;dummy={$time}{$_conf['k_at_a']}"{$_conf['k_accesskey_at']['latest']}>{$_conf['k_accesskey_st']['latest']}{$latest_st}{$latest_show_res_num}</a>
EOP;
}

// {{{ 検索

$read_navi_filter = <<<EOP
<a href="read_filter_k.php?{$host_bbs_key_q}{$ttitle_en_q}{$_conf['k_at_a']}">{$find_st}</a>
EOP;
$read_navi_filter_btm = <<<EOP
<a href="read_filter_k.php?{$host_bbs_key_q}{$ttitle_en_q}{$_conf['k_at_a']}"{$_conf['k_accesskey_at']['filter']}>{$_conf['k_accesskey_st']['filter']}{$find_st}</a>
EOP;

// }}}

// IC2リンク
if ($_conf['expack.ic2.enabled'] && $_conf['expack.ic2.thread_imagelink']) {
    $cnt = '';
    if ($_conf['expack.ic2.thread_imagecount']) {
        require_once P2EX_LIB_DIR . '/ic2_getcount.inc.php';
        try {
            $cnt = '(' . getIC2ImageCount($aThread->ttitle) . ')';
        }
        catch (Exception $e) {
            $cnt = '(?)';
        }
    }
    $htm['ic2navi'] = '<a href="iv2.php?field=memo&amp;keyword='
        . rawurlencode($aThread->ttitle)
        . '&amp;session_no_close=1'
        . '&amp;b=' . ($_conf['iphone'] ? 'i' : 'k')
        . '">IC2' . $cnt . '</a>';
}

//====================================================================
// 検索時の特別な処理
//====================================================================
if ($filter_hits !== null) {
    include P2_LIB_DIR . '/read_filter_k.inc.php';
}

//====================================================================
// HTMLプリント
//====================================================================

// {{{ ツールバー部分HTML
$similar_q = '&amp;itaj_en=' . UrlSafeBase64::encode($aThread->itaj)
           . '&amp;method=similar&amp;word=' . rawurlencode($aThread->ttitle_hc)
           . '&amp;refresh=1';
$itaj_hd = p2h($aThread->itaj);
$toolbar_right_ht = <<<EOTOOLBAR
<a href="{$_conf['subject_php']}?{$host_bbs_key_q}{$_conf['k_at_a']}"{$_conf['k_accesskey_at']['up']}>{$_conf['k_accesskey_st']['up']}{$itaj_hd}</a>
<a href="info.php?{$host_bbs_key_q}{$ttitle_en_q}{$_conf['k_at_a']}"{$_conf['k_accesskey_at']['info']}>{$_conf['k_accesskey_st']['info']}{$info_st}</a>
<a href="info.php?{$host_bbs_key_q}{$ttitle_en_q}&amp;dele=1{$_conf['k_at_a']}"{$_conf['k_accesskey_at']['dele']}>{$_conf['k_accesskey_st']['dele']}{$delete_st}</a>
<a href="{$motothre_url}" target="_blank">{$moto_thre_st}</a>
<a href="{$_conf['subject_php']}?{$host_bbs_key_q}{$similar_q}{$_conf['k_at_a']}">{$siml_thre_st}</a>
EOTOOLBAR;
// }}}

//=====================================
//!empty($_GET['nocache']) and P2Util::header_nocache();
echo $_conf['doctype'];
echo <<<EOHEADER
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
{$_conf['extra_headers_ht']}
<title>{$ptitle_ht}</title>\n
EOHEADER;

echo <<<EOP
</head>
<body{$_conf['k_colors']}>\n
EOP;

P2Util::printInfoHtml();

// スレが板サーバになければ============================
if ($aThread->diedat) {

    if ($aThread->getdat_error_msg_ht) {
        $diedat_msg = $aThread->getdat_error_msg_ht;
    } else {
        $diedat_msg = $aThread->getDefaultGetDatErrorMessageHTML();
    }

    $motothre_ht = "<a href=\"{$motothre_url}\" target=\"_blank\">{$motothre_url}</a>";

    echo $diedat_msg;
    echo "<div>";
    echo  $motothre_ht;
    echo "</div>";
    echo "<hr>";

    // 既得レスがなければツールバー表示
    if (!$aThread->rescount) {
        echo <<<EOP
<div class="toolbar">{$toolbar_right_ht}</div>
EOP;
    }
}


if (($aThread->rescount or $_GET['one'] && !$aThread->diedat) && empty($_GET['renzokupop'])) {

    echo <<<EOP
<div class="navi">{$htm['read_navi_range']}
{$read_navi_previous}
{$read_navi_next}
{$read_navi_latest}
{$htm['ic2navi']}
<a href="#footer"{$_conf['k_accesskey_at']['bottom']}>{$_conf['k_accesskey_st']['bottom']}▼</a></div>\n
EOP;

}

echo "<hr>";
echo "<h3><font color=\"{$STYLE['mobile_read_ttitle_color']}\">{$aThread->ttitle_hd}</font></h3>\n";

$filter_fields = array(
    ResFilter::FIELD_HOLE => '', 
    ResFilter::FIELD_MESSAGE => 'ﾒｯｾｰｼﾞが',
    ResFilter::FIELD_NAME => '名前が',
    ResFilter::FIELD_MAIL => 'ﾒｰﾙが',
    ResFilter::FIELD_DATE => '日付が',
    ResFilter::FIELD_ID => 'IDが',
);


if ($do_filtering) {
    $resFilter = ResFilter::getFilter();
    echo "検索結果: {$filter_fields[$resFilter->field]}";
    echo "&quot;{$hd['word']}&quot;を";
    echo ($resFilter->match == ResFilter::MATCH_ON) ? '含む' : '含まない';
}
if ($_GET['showbl']) {
    echo  p2h($aThread->resrange['start']) . 'へのﾚｽ';
}

echo '<hr>';

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
