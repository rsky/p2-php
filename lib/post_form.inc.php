<?php
/**
 *  rep2 - 書き込みフォーム
 */

if (!isset($popup)) {
    $popup = 0;
}
if (!isset($newthread_hidden_ht)) {
    $newthread_hidden_ht = '';
}
if (!isset($readnew_hidden_ht)) {
    $readnew_hidden_ht = '';
}

// 公式p2を使ったスレ立ては未実装 (P2Client.php)
if ($newthread_hidden_ht !== '') {
    $htm['p2res'] = '';
}

if ($_conf['ktai']) {
    $htm['k_br'] = '<br>';
    $htm['kaiko_on_js'] = '';
    $htm['kaiko_set_hidden_js'] = '';
    $htm['table_begin'] = '';
    $htm['table_break1'] = '';
    $htm['table_break2'] = '';
    $htm['table_end'] = '<br>';
    if ($_conf['iphone']) {
        $htm['options'] .= <<<EOP
<div style="text-align:right;">
<!-- <input type="checkbox" id="autocorrect" onclick="iutil.toggleAutocorrect('MESSAGE', this.checked);"><label for="autocorrect">autocorrect</label> -->
<input type="button" onclick="iutil.shrinkTextarea('MESSAGE');iutil.shrinkTextarea('MESSAGE');" value="－">
<input type="button" onclick="iutil.expandTextarea('MESSAGE');" value="＋">
</div>
EOP;
        $htm['name_label'] = '<span style="display:inline-block;width:3em;text-align:right;">名前</span>：';
        $htm['mail_label'] = '<span style="display:inline-block;width:3em;text-align:right;">E-mail</span>：';
        $htm['name_extra_at'] = ' autocorrect="off" autocapitalize="off"';
        $htm['mail_extra_at'] = ' autocorrect="off" autocapitalize="off"';
        $htm['msg_extra_at'] = ' autocorrect="off" autocapitalize="off"';
        $htm['submit_extra_at'] = '';
        if ($_conf['expack.editor.mobile.savedraft'] != '0' && $_conf['expack.editor.mobile.savedraft.interval'] > 0) {
            $htm['kaiko_on_js'] = ' onfocus="DraftKakiko.startAutoSave(this.form, ' . ($_conf['expack.editor.mobile.savedraft.interval'] * 1000) . ')"';
        }
    } else {
        $htm['name_label'] = '名前：';
        $htm['mail_label'] = 'E-mail：';
        $htm['name_extra_at'] = '';
        $htm['mail_extra_at'] = '';
        $htm['msg_extra_at'] = '';
        $htm['submit_extra_at'] = '';
    }
} else {
    $htm['k_br'] = '';
    if ($_conf['expack.editor.dpreview']) {
        $htm['kaiko_on_js_fmt'] = ' onfocus="%1$s" onkeyup="if(%2$s){%1$s}DPSetMsg()"';
    } else {
        $htm['kaiko_on_js_fmt'] = ' onfocus="%1$s" onkeyup="if(%2$s){%1$s}"';
    }
    $htm['kaiko_on_js_func'] = sprintf("adjustTextareaRows(this,%d,2)", $STYLE['post_msg_rows']);
    if ($_conf['expack.editor.savedraft'] != '0' && $_conf['expack.editor.savedraft.interval'] > 0) {
        $htm['kaiko_on_js_func'] = 'DraftKakiko.startAutoSave(this.form, ' . ($_conf['expack.editor.savedraft.interval'] * 1000) . '); ' . $htm['kaiko_on_js_func'];
    }
    $htm['kaiko_on_js_cond'] = '!event||((event.keyCode&&(event.keyCode==8||event.keyCode==13))||event.ctrlKey||event.metaKey||event.altKey)';
    $htm['kaiko_on_js'] = sprintf($htm['kaiko_on_js_fmt'], $htm['kaiko_on_js_func'], p2h($htm['kaiko_on_js_cond']));
    //$htm['kaiko_on_js'] .= ' ondblclick="this.rows=this.value.split(/\r\n|\r|\n/).length+1"';
    $htm['kaiko_set_hidden_js'] = ' onclick="setHiddenValue(this);"';
    $htm['table_begin'] = '<table border="0" cellpadding="0" cellspacing="0"><tr><td align="left" colspan="2">';
    $htm['table_break1'] = '</td></tr><tr><td align="left">';
    $htm['table_break2'] = '</td><td align="right">';
    $htm['table_end'] = '</td></tr></table>';
    $htm['name_label'] = '<label for="FROM">名前</label>：';
    $htm['mail_label'] = '<label for="mail">E-mail</label>：';
    $htm['name_extra_at'] = ' tabindex="1"';
    $htm['mail_extra_at'] = ' tabindex="2"';
    $htm['msg_extra_at'] = ' tabindex="3"';
    $htm['submit_extra_at'] = ' tabindex="4"';
}

// +Wiki:sambaタイマー
if ($_conf['wiki.samba_timer']) {
    require_once P2_LIB_DIR . '/wiki/Samba.php';
    $samba = new Samba();
    $htm['samba'] .= $samba->createTimer($samba->getSamba($host, $bbs));
}

// 下書き保存
$savedraft = '';
if ((!$_conf['ktai'] && $_conf['expack.editor.savedraft'] != 0) ||
    ($_conf['iphone'] && $_conf['expack.editor.mobile.savedraft'] != 0)) {
    $savedraft = <<<EOP
<input id="post_draft_button" type="button" value="下書き保存" onclick="DraftKakiko.saveDraftForm(this.form)">
<span id="post_draft_msg" class="autosave-info"></span>
EOP;
} elseif ($_conf['ktai'] && $_conf['expack.editor.mobile.savedraft']) {
    $savedraft = <<<EOP
<input type="submit" name="savedraft" value="下書保存">
EOP;
}

// 文字コード判定用文字列を先頭に仕込むことでmb_convert_variables()の自動判定を助ける
$htm['post_form'] = <<<EOP
{$htm['disable_js']}
{$htm['resform_ttitle']}
<form id="resform" method="POST" action="./post.php" accept-charset="{$_conf['accept_charset']}"{$onsubmit_at}>
{$htm['subject']}
{$htm['maru_post']}
{$htm['name_label']}<input id="FROM" name="FROM" type="text" value="{$hd['FROM']}"{$name_size_at}{$htm['name_extra_at']}>{$htm['k_br']}
{$htm['mail_label']}<input id="mail" name="mail" type="text" value="{$hd['mail']}"{$mail_size_at}{$on_check_sage}{$htm['mail_extra_at']}>{$htm['k_br']}
{$htm['sage_cb']}
{$htm['options']}
{$htm['table_begin']}
<textarea id="MESSAGE" name="MESSAGE" rows="{$STYLE['post_msg_rows']}"{$msg_cols_at}{$wrap_at}{$htm['kaiko_on_js']}{$htm['msg_extra_at']}>{$hd['MESSAGE']}</textarea>
{$htm['table_break1']}
{$htm['dpreview_onoff']}
{$htm['dpreview_amona']}
{$htm['src_fix']}
{$htm['block_submit']}
{$htm['table_break2']}
<input id="kakiko_submit" type="submit" name="submit" value="{$submit_value}"{$htm['kaiko_set_hidden_js']}{$htm['submit_extra_at']}>
{$htm['beres']}
{$htm['p2res']}
{$htm['table_end']}
{$htm['samba']}
{$htm['k_br']}{$savedraft}

<input type="hidden" name="bbs" value="{$bbs}">
<input type="hidden" name="key" value="{$key}">
<input type="hidden" name="time" value="{$time}">

<input type="hidden" name="host" value="{$host}">
<input type="hidden" name="popup" value="{$popup}">
<input type="hidden" name="rescount" value="{$rescount}">
<input type="hidden" name="ttitle_en" value="{$ttitle_en}">
<input type="hidden" name="csrfid" value="{$csrfid}">
{$newthread_hidden_ht}{$readnew_hidden_ht}
{$_conf['detect_hint_input_ht']}{$_conf['k_input_ht']}
</form>
{$upload_form}
{$htm['options_k']}\n
EOP;

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
