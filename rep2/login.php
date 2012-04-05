<?php
/**
 * rep2 - ログイン
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

$csrfid = P2Util::getCsrfId(__FILE__);

//=========================================================
// 書き出し用変数
//=========================================================
$p_htm = array();

// 表示文字
$p_str = array(
    'ptitle'        => 'rep2認証ユーザ管理',
    'autho_user'    => '認証ユーザ',
    'logout'        => 'ログアウト',
    'password'      => 'パスワード',
    'login'         => 'ログイン',
    'user'          => 'ユーザ'
);

// 携帯用表示文字列変換
if ($_conf['ktai'] && function_exists('mb_convert_kana')) {
    foreach ($p_str as $k => $v) {
        $p_str[$k] = mb_convert_kana($v, 'rnsk');
    }
}

// （携帯）ログイン用URL
//$user_u_q = $_conf['ktai'] ? "?user={$_login->user_u}" : '';
//$url = rtrim(dirname(P2Util::getMyUrl()), '/') . '/' . $user_u_q . '&amp;b=k';
$url = rtrim(dirname(P2Util::getMyUrl()), '/') . '/?b=k';

$p_htm['ktai_url'] = '携帯'.$p_str['login'].'用URL <a href="'.$url.'" target="_blank">'.$url.'</a><br>';

//====================================================
// ユーザ登録処理
//====================================================
if (isset($_POST['form_new_login_pass'])) {
    if (!isset($_POST['csrfid']) || $_POST['csrfid'] != $csrfid) {
        p2die('不正なポストです');
    }

    $new_login_pass = $_POST['form_new_login_pass'];

    // 入力チェック
    if (!preg_match('/^[\@-\~]+$/', $new_login_pass)) {
        P2Util::pushInfoHtml("<p>rep2 error: {$p_str['password']}を半角英数字で入力して下さい。</p>");
    } elseif ($new_login_pass != $_POST['form_new_login_pass2']) {
        P2Util::pushInfoHtml("<p>rep2 error: {$p_str['password']} と {$p_str['password']} (確認) が一致しませんでした。</p>");

    // パスワード変更登録処理を行う
    } else {
        $login_user = strval($_login->user_u);
        $hashed_login_pass = sha1($new_login_pass);
        $login_user_repr = var_export($login_user, true);
        $login_pass_repr = var_export($hashed_login_pass, true);
        $auth_user_cont = <<<EOP
<?php
\$rec_login_user_u = {$login_user_repr};
\$rec_login_pass_x = {$login_pass_repr};\n
EOP;
        $fp = @fopen($_conf['auth_user_file'], 'wb');
        if (!$fp) {
            p2die("{$_conf['auth_user_file']} を保存できませんでした。認証ユーザ登録失敗。");
        }
        flock($fp, LOCK_EX);
        fputs($fp, $auth_user_cont);
        flock($fp, LOCK_UN);
        fclose($fp);

        P2Util::pushInfoHtml('<p>○認証パスワードを変更登録しました</p>');
    }
}

//====================================================
// 補助認証
//====================================================
// Cookie認証
if ($_login->checkUserPwWithCid($_COOKIE['cid'])) {
    $p_htm['auth_cookie'] = <<<EOP
Cookie認証登録済[<a href="cookie.php?ctl_keep_login=1{$_conf['k_at_a']}">解除</a>]<br>
EOP;
} else {
    if ($_login->pass_x) {
        $p_htm['auth_cookie'] = <<<EOP
[<a href="cookie.php?ctl_keep_login=1&amp;keep_login=1{$_conf['k_at_a']}">Cookieにログイン状態を保持</a>]<br>
EOP;
    }
}

//====================================================
// Cookie認証チェック
//====================================================
if (!empty($_REQUEST['check_keep_login'])) {
    $keep_login = isset($_REQUEST['keep_login']) ? $_REQUEST['keep_login'] : '';
    if ($_login->checkUserPwWithCid($_COOKIE['cid'])) {
        if ($keep_login === '1') {
            $info_msg_ht = '<p>○Cookie認証登録完了</p>';
        } else {
            $info_msg_ht = '<p>×Cookie認証解除失敗</p>';
        }

    } else {
        if ($keep_login === '1') {
            $info_msg_ht = '<p>×Cookie認証登録失敗</p>';
        } else  {
            $info_msg_ht = '<p>○Cookie認証解除完了</p>';
        }
    }

    P2Util::pushInfoHtml($info_msg_ht);
}

//====================================================
// 認証ユーザ登録フォーム
//====================================================
if ($_conf['ktai']) {
    $login_form_ht = <<<EOP
<hr>
<form id="login_change" method="POST" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
    {$p_str['password']}の変更<br>
    {$_conf['k_input_ht']}
    <input type="hidden" name="csrfid" value="{$csrfid}">
    新しい{$p_str['password']}:<br>
    <input type="password" name="form_new_login_pass"><br>
    新しい{$p_str['password']} (確認):<br>
    <input type="password" name="form_new_login_pass2"><br>
    <input type="submit" name="submit" value="変更登録">
</form>
<hr>
<div class="center">{$_conf['k_to_index_ht']}</div>
EOP;
} else {
    $login_form_ht = <<<EOP
<form id="login_change" method="POST" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
    {$p_str['password']}の変更<br>
    {$_conf['k_input_ht']}
    <input type="hidden" name="csrfid" value="{$csrfid}">
    <table border="0">
        <tr>
            <td>新しい{$p_str['password']}</td>
            <td><input type="password" name="form_new_login_pass"></td>
        </tr>
        <tr>
            <td>新しい{$p_str['password']} (確認)</td>
            <td><input type="password" name="form_new_login_pass2"></td>
        </tr>
    </table>
    <input type="submit" name="submit" value="変更登録">
</form>
EOP;
}

//=========================================================
// HTMLプリント
//=========================================================
P2Util::header_nocache();
echo $_conf['doctype'];
echo <<<EOP
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>{$p_str['ptitle']}</title>\n
EOP;

if (!$_conf['ktai']) {
    echo <<<EOP
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=login&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}

$body_at = ($_conf['ktai']) ? $_conf['k_colors'] : ' onload="setWinTitle();"';
echo <<<EOP
</head>
<body{$body_at}>
EOP;

if (!$_conf['ktai']) {
    echo <<<EOP
<p id="pan_menu"><a href="setting.php">ログイン管理</a> &gt; {$p_str['ptitle']}</p>
EOP;
}

// 情報表示
P2Util::printInfoHtml();

echo '<p id="login_status">';
echo <<<EOP
{$p_str['autho_user']}: {$_login->user_u}<br>
{$p_htm['auth_cookie']}
<br>
[<a href="./index.php?logout=1" target="_parent">{$p_str['logout']}する</a>]
EOP;
echo '</p>';

echo $login_form_ht;

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
