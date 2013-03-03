<?php
/**
 * rep2 - スレッド表示 -  フッタ部分 -  for read.php
 */

//=====================================================================
// ■フッタ
//=====================================================================

if ($_conf['bottom_res_form']) {

    $bbs = $aThread->bbs;
    $key = $aThread->key;
    $host = $aThread->host;
    $rescount = $aThread->rescount;
    $ttitle_en = UrlSafeBase64::encode($aThread->ttitle);

    $submit_value = '書き込む';

    $key_idx = $aThread->keyidx;

    // フォームのオプション読み込み
    require_once P2_LIB_DIR . '/post_form_options.inc.php';

    $htm['resform_ttitle'] = <<<EOP
<p><b class="thre_title">{$aThread->ttitle_hd}</b></p>
EOP;

    require_once P2_LIB_DIR . '/post_form.inc.php';

    // フォーム
    $res_form_ht = <<<EOP
<div id="kakiko">
{$htm['dpreview']}
{$htm['post_form']}
{$htm['dpreview2']}
</div>\n
EOP;

    $onmouse_showform_ht = <<<EOP
 onmouseover="document.getElementById('kakiko').style.display = 'block';"
EOP;

}

// IC2リンク、件数
if ($_conf['expack.ic2.enabled'] && $_conf['expack.ic2.thread_imagelink']) {
    $htm['ic2navi'] = '<a href="iv2.php?field=memo&amp;keyword='
        . rawurlencode($aThread->ttitle)
        . '" target="_blank">キャッシュ画像'
        . ($_conf['expack.ic2.thread_imagecount'] ? '<span id="ic2_count_f"></span>' : '')
        . '</a> ';
}

// ============================================================
if ($aThread->rescount or (!empty($_GET['one']) && !$aThread->diedat)) { // and (!$_GET['renzokupop'])

    if (!$aThread->diedat) {
        if (!empty($_conf['disable_res'])) {
            $htm['dores'] = <<<EOP
<a href="{$motothre_url}" target="_blank">{$dores_st}</a>
EOP;
        } else {
            $htm['dores'] = <<<EOP
<a href="post_form.php?{$host_bbs_key_q}&amp;rescount={$aThread->rescount}{$ttitle_en_q}" target="_self" onclick="return OpenSubWin('post_form.php?{$host_bbs_key_q}&amp;rescount={$aThread->rescount}{$ttitle_en_q}&amp;popup=1',{$STYLE['post_pop_size']},1,0)"{$onmouse_showform_ht}>{$dores_st}</a>
EOP;
        }

        $res_form_ht_pb = $res_form_ht;
    } else {
        $htm['dores'] = '';
        $res_form_ht_pb = '';
    }

    if ($res1['body']) {
        $q_ichi = $res1['body']." | ";
    } else {
        $q_ichi = '';
    }

    // レスのすばやさ
    if ($spd_st = $aThread->getTimePerRes() and $spd_st != '-') {
        $htm['spd'] = '<span class="spd" title="すばやさ＝時間/レス">' . $spd_st . '</span>';
    } else {
        $htm['spd'] = '';
    }

    // datサイズ
    if (file_exists($aThread->keydat) && $dsize_ht = filesize($aThread->keydat)) {
        $htm['dsize'] = sprintf('<span class="spd" title="%s">%01.1fKB</span> |', 'datサイズ', $dsize_ht / 1024);
    } else {
        $htm['dsize'] = '';
    }

    // レス番指定移動
    $htm['goto'] = <<<GOTO
            <form method="get" action="{$_conf['read_php']}" class="inline-form">
                <input type="hidden" name="host" value="{$aThread->host}">
                <input type="hidden" name="bbs" value="{$aThread->bbs}">
                <input type="hidden" name="key" value="{$aThread->key}">
                <input type="text" size="5" name="ls" value="{$aThread->ls}">
                <input type="submit" value="go">
            </form>
GOTO;

    // {{{ フィルタヒットがあった場合、次Xと続きを読むを更新
    /*
    //if (!$read_navi_next_isInvisible) {
    $read_navi_next = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->resrange['to']}-{$after_rnum}{$offline_range_q}&amp;nt={$newtime}{$read_navi_next_anchor}\">{$next_st}{$rnum_range}</a>";
    //}

    $read_footer_navi_new = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->resrange['to']}-{$offline_q}\" accesskey=\"r\">{$tuduki_st}</a>";
    */

    if (!empty($GLOBALS['last_hit_resnum'])) {
        $read_navi_next_anchor = "";
        if ($GLOBALS['last_hit_resnum'] == $aThread->rescount) {
            $read_navi_next_anchor = "#r{$aThread->rescount}";
        }
        $after_rnum = $GLOBALS['last_hit_resnum'] + $rnum_range;
        $read_navi_next = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$GLOBALS['last_hit_resnum']}-{$after_rnum}{$offline_range_q}&amp;nt={$newtime}{$read_navi_next_anchor}\">{$next_st}{$rnum_range}</a>";

        // 「続きを読む」
        $read_footer_navi_new = "<a href=\"{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$GLOBALS['last_hit_resnum']}-{$offline_q}\" accesskey=\"r\">{$tuduki_st}</a>";
    }
    // }}}

    // ■プリント
    echo <<<EOP
<hr>
<table id="footer" class="toolbar">
    <tr>
        <td class="lblock">
            {$q_ichi}
            <a href="{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls=all">{$all_st}</a>
            {$read_navi_previous}
            {$read_navi_next}
            <a href="{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls=l{$latest_show_res_num}">{$latest_st}{$latest_show_res_num}</a>
            {$htm['goto']}
            | {$read_footer_navi_new}
            | {$htm['dores']}
            {$htm['dsize']}
            {$htm['spd']}
        </td>
        <td class="rblock">{$htm['ic2navi']}{$htm['p2frame']} {$toolbar_right_ht}</td>
        <td class="rblock"><a href="#header">▲</a></td>
    </tr>
</table>
{$res_form_ht_pb}
EOP;

    if ($diedat_msg) {
        echo "<hr>";
        echo $diedat_msg;
        echo "<p>";
        echo $motothre_ht;
        echo "</p>";
    }
}

if (!empty($_GET['showres'])) {
    echo <<<EOP
    <script type="text/javascript">
    <![CDATA[
    document.getElementById('kakiko').style.display = 'block';
    //]]>
    </script>\n
EOP;
}

if ($_conf['expack.ic2.enabled']) {
    include P2EX_LIB_DIR . '/ImageCache2/templates/info.tpl.html';
}

// ====
echo '</body></html>';

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
