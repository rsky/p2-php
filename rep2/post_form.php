<?php
/**
 * rep2 - レス書き込みフォーム
 */

define('P2_SESSION_CLOSE_AFTER_AUTHENTICATION', 0);
require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

//==================================================
// 変数
//==================================================
if (empty($_GET['host'])) {
    // 引数エラー
    p2die('host が指定されていません');
} else {
    $host = $_GET['host'];
}

$bbs = isset($_GET['bbs']) ? $_GET['bbs'] : '';
$key = isset($_GET['key']) ? $_GET['key'] : '';

$rescount = isset($_GET['rescount']) ? intval($_GET['rescount']) : 1;
$popup = isset($_GET['popup']) ? intval($_GET['popup']) : 0;

$itaj = P2Util::getItaName($host, $bbs);
if (!$itaj) {
    $itaj = $bbs;
}
$itaj_hd = p2h($itaj, false);

$ttitle_en = isset($_GET['ttitle_en']) ? $_GET['ttitle_en'] : '';
$ttitle = (strlen($ttitle_en) > 0) ? UrlSafeBase64::decode($ttitle_en) : '';
$ttitle_hd = p2h($ttitle);

$key_idx = P2Util::idxDirOfHostBbs($host, $bbs) . $key . '.idx';

// フォームのオプション読み込み
include P2_LIB_DIR . '/post_form_options.inc.php';

// 表示指定
if (!$_conf['ktai']) {
    $class_ttitle = ' class="thre_title"';
    $target_read = ' target="read"';
    $sub_size_at = ' size="40"';
} else {
    $class_ttitle = '';
    $target_read = '';
    $sub_size_at = '';
}

// {{{ スレ立てなら
if (!empty($_GET['newthread'])) {
    $ptitle = "{$itaj_hd} - 新規スレッド作成";

    // machibbs、JBBS@したらば なら
    if (P2Util::isHostMachiBbs($host) or P2Util::isHostJbbsShitaraba($host)) {
        $submit_value = '新規書き込み';
    // 2chなら
    } else {
        $submit_value = '新規スレッド作成';
    }

    $htm['subject'] = <<<EOP
<b><span{$class_ttitle}>タイトル</span></b>：<input type="text" name="subject"{$sub_size_at} value="{$hd['subject']}"><br>
EOP;
    if ($_conf['ktai']) {
        $htm['subject'] = "<a href=\"{$_conf['subject_php']}?host={$host}&amp;bbs={$bbs}{$_conf['k_at_a']}\">{$itaj_hd}</a><br>".$htm['subject'];
    }
    $newthread_hidden_ht = '<input type="hidden" name="newthread" value="1">';
// }}}

// {{{ 書き込みなら
} else {
    $ptitle = "{$itaj_hd} - レス書き込み";

    $submit_value = "書き込む";

    $htm['resform_ttitle'] = <<<EOP
<p><b><a{$class_ttitle} href="{$_conf['read_php']}?host={$host}&amp;bbs={$bbs}&amp;key={$key}{$_conf['k_at_a']}"{$target_read}>{$ttitle_hd}</a></b></p>
EOP;
    $newthread_hidden_ht = '';
}
// }}}

$readnew_hidden_ht = !empty($_GET['from_read_new']) ? '<input type="hidden" name="from_read_new" value="1">' : '';


//==========================================================
// HTMLプリント
//==========================================================
echo $_conf['doctype'];
echo <<<EOHEADER
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>{$ptitle}</title>\n
EOHEADER;

if (!$_conf['ktai']) {
    echo <<<EOP
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=post&amp;skin={$skin_en}">\n
EOP;
    if ($_conf['expack.editor.dpreview']) {
        echo "<link rel=\"stylesheet\" href=\"css.php?css=prvw&amp;skin={$skin_en}\" type=\"text/css\">\n";
    }
    echo <<<EOP
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/post_form.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}
// 下書き保存JS
if ((!$_conf['ktai'] && $_conf['expack.editor.savedraft']) ||
    ($_conf['iphone'] && $_conf['expack.editor.mobile.savedraft'])) {
    echo <<<EOP
    <script type="text/javascript" src="js/post_draft.js?{$_conf['p2_version_id']}"></script>
EOP;
}

$body_at = ($_conf['ktai']) ? $_conf['k_colors'] : ' onload="setFocus(\'MESSAGE\'); checkSage();"';
echo <<<EOP
</head>
<body{$body_at}>\n
EOP;

P2Util::printInfoHtml();

// $htm['post_form'] を取得
require_once P2_LIB_DIR . '/post_form.inc.php';

echo $htm['orig_msg'];
echo $htm['dpreview'];
echo $htm['post_form'];
echo $htm['dpreview2'];

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
