<?php
/**
 * rep2 - ���O�C��
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ���[�U�F��

$csrfid = P2Util::getCsrfId(__FILE__);

//=========================================================
// �����o���p�ϐ�
//=========================================================
$p_htm = array();

// �\������
$p_str = array(
    'ptitle'        => 'rep2�F�؃��[�U�Ǘ�',
    'autho_user'    => '�F�؃��[�U',
    'logout'        => '���O�A�E�g',
    'password'      => '�p�X���[�h',
    'login'         => '���O�C��',
    'user'          => '���[�U'
);

// �g�їp�\��������ϊ�
if ($_conf['ktai'] && function_exists('mb_convert_kana')) {
    foreach ($p_str as $k => $v) {
        $p_str[$k] = mb_convert_kana($v, 'rnsk');
    }
}

// �i�g�сj���O�C���pURL
//$user_u_q = $_conf['ktai'] ? "?user={$_login->user_u}" : '';
//$url = rtrim(dirname(P2Util::getMyUrl()), '/') . '/' . $user_u_q . '&amp;b=k';
$url = rtrim(dirname(P2Util::getMyUrl()), '/') . '/?b=k';

$p_htm['ktai_url'] = '�g��'.$p_str['login'].'�pURL <a href="'.$url.'" target="_blank">'.$url.'</a><br>';

//====================================================
// ���[�U�o�^����
//====================================================
if (isset($_POST['form_new_login_pass'])) {
    if (!isset($_POST['csrfid']) || $_POST['csrfid'] != $csrfid) {
        p2die('�s���ȃ|�X�g�ł�');
    }

    $new_login_pass = $_POST['form_new_login_pass'];

    // ���̓`�F�b�N
    if (!preg_match('/^[\@-\~]+$/', $new_login_pass)) {
        P2Util::pushInfoHtml("<p>rep2 error: {$p_str['password']}�𔼊p�p�����œ��͂��ĉ������B</p>");
    } elseif ($new_login_pass != $_POST['form_new_login_pass2']) {
        P2Util::pushInfoHtml("<p>rep2 error: {$p_str['password']} �� {$p_str['password']} (�m�F) ����v���܂���ł����B</p>");

    // �p�X���[�h�ύX�o�^�������s��
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
            p2die("{$_conf['auth_user_file']} ��ۑ��ł��܂���ł����B�F�؃��[�U�o�^���s�B");
        }
        flock($fp, LOCK_EX);
        fputs($fp, $auth_user_cont);
        flock($fp, LOCK_UN);
        fclose($fp);

        P2Util::pushInfoHtml('<p>���F�؃p�X���[�h��ύX�o�^���܂���</p>');
    }
}

//====================================================
// �⏕�F��
//====================================================
// Cookie�F��
if ($_login->checkUserPwWithCid($_COOKIE['cid'])) {
    $p_htm['auth_cookie'] = <<<EOP
Cookie�F�ؓo�^��[<a href="cookie.php?ctl_keep_login=1{$_conf['k_at_a']}">����</a>]<br>
EOP;
} else {
    if ($_login->pass_x) {
        $p_htm['auth_cookie'] = <<<EOP
[<a href="cookie.php?ctl_keep_login=1&amp;keep_login=1{$_conf['k_at_a']}">Cookie�Ƀ��O�C����Ԃ�ێ�</a>]<br>
EOP;
    }
}

//====================================================
// Cookie�F�؃`�F�b�N
//====================================================
if (!empty($_REQUEST['check_keep_login'])) {
    $keep_login = isset($_REQUEST['keep_login']) ? $_REQUEST['keep_login'] : '';
    if ($_login->checkUserPwWithCid($_COOKIE['cid'])) {
        if ($keep_login === '1') {
            $info_msg_ht = '<p>��Cookie�F�ؓo�^����</p>';
        } else {
            $info_msg_ht = '<p>�~Cookie�F�؉������s</p>';
        }

    } else {
        if ($keep_login === '1') {
            $info_msg_ht = '<p>�~Cookie�F�ؓo�^���s</p>';
        } else  {
            $info_msg_ht = '<p>��Cookie�F�؉�������</p>';
        }
    }

    P2Util::pushInfoHtml($info_msg_ht);
}

//====================================================
// �F�؃��[�U�o�^�t�H�[��
//====================================================
if ($_conf['ktai']) {
    $login_form_ht = <<<EOP
<hr>
<form id="login_change" method="POST" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
    {$p_str['password']}�̕ύX<br>
    {$_conf['k_input_ht']}
    <input type="hidden" name="csrfid" value="{$csrfid}">
    �V����{$p_str['password']}:<br>
    <input type="password" name="form_new_login_pass"><br>
    �V����{$p_str['password']} (�m�F):<br>
    <input type="password" name="form_new_login_pass2"><br>
    <input type="submit" name="submit" value="�ύX�o�^">
</form>
<hr>
<div class="center">{$_conf['k_to_index_ht']}</div>
EOP;
} else {
    $login_form_ht = <<<EOP
<form id="login_change" method="POST" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
    {$p_str['password']}�̕ύX<br>
    {$_conf['k_input_ht']}
    <input type="hidden" name="csrfid" value="{$csrfid}">
    <table border="0">
        <tr>
            <td>�V����{$p_str['password']}</td>
            <td><input type="password" name="form_new_login_pass"></td>
        </tr>
        <tr>
            <td>�V����{$p_str['password']} (�m�F)</td>
            <td><input type="password" name="form_new_login_pass2"></td>
        </tr>
    </table>
    <input type="submit" name="submit" value="�ύX�o�^">
</form>
EOP;
}

//=========================================================
// HTML�v�����g
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
<p id="pan_menu"><a href="setting.php">���O�C���Ǘ�</a> &gt; {$p_str['ptitle']}</p>
EOP;
}

// ���\��
P2Util::printInfoHtml();

echo '<p id="login_status">';
echo <<<EOP
{$p_str['autho_user']}: {$_login->user_u}<br>
{$p_htm['auth_cookie']}
<br>
[<a href="./index.php?logout=1" target="_parent">{$p_str['logout']}����</a>]
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
