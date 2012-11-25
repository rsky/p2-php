<?php
/**
 * rep2 - �^�C�g���y�[�W
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ���[�U�F��

//=========================================================
// �ϐ�
//=========================================================

if (!empty($GLOBALS['pref_dir_realpath_failed_msg'])) {
    P2Util::pushInfoHtml('<p>' . $GLOBALS['pref_dir_realpath_failed_msg'] . '</p>');
}

$p2web_url_r = P2Util::throughIme($_conf['p2web_url']);
$expack_url_r = P2Util::throughIme($_conf['expack.web_url']);
$expack_dl_url_r = P2Util::throughIme($_conf['expack.download_url']);
$expack_hist_url_r = P2Util::throughIme($_conf['expack.history_url']);

// {{{ �f�[�^�ۑ��f�B���N�g���̃p�[�~�b�V�����̒��ӂ����N����

P2Util::checkDirWritable($_conf['dat_dir']);
$checked_dirs[] = $_conf['dat_dir']; // �`�F�b�N�ς݂̃f�B���N�g�����i�[����z���

// �܂��`�F�b�N���Ă��Ȃ����
if (!in_array($_conf['idx_dir'], $checked_dirs)) {
    P2Util::checkDirWritable($_conf['idx_dir']);
    $checked_dirs[] = $_conf['idx_dir'];
}
if (!in_array($_conf['pref_dir'], $checked_dirs)) {
    P2Util::checkDirWritable($_conf['pref_dir']);
    $checked_dirs[] = $_conf['pref_dir'];
}

// }}}

//=========================================================
// �O����
//=========================================================
// ��ID 2ch �I�[�g���O�C��
if ($array = P2Util::readIdPw2ch()) {
    list($login2chID, $login2chPW, $autoLogin2ch) = $array;
    if ($autoLogin2ch) {
        require_once P2_LIB_DIR . '/login2ch.inc.php';
        login2ch();
    }
}

//=========================================================
// �v�����g�ݒ�
//=========================================================
// �ŐV�Ń`�F�b�N
$newversion_found = '';
if (!empty($_conf['updatan_haahaa'])) {
    $newversion_found = checkUpdatan();
}

// ���O�C�����[�U���
$htm['auth_user'] = "<p>���O�C�����[�U: {$_login->user_u} - " . date("Y/m/d (D) G:i") . "</p>\n";

// �i�g�сj���O�C���pURL
$base_url = rtrim(dirname(P2Util::getMyUrl()), '/') . '/';
$url_b = $base_url . '?user=' . rawurlencode($_login->user_u) . '&b=';
$url_b_ht = p2h($url_b);

// �g�їp�r���[���J���u�b�N�}�[�N���b�g
$bookmarklet = <<<JS
(function (u, w, v, x, y) {
    var t;
    if (typeof window.outerHeight === 'number') {
        t = y + window.outerHeight;
        if (v < t){
            v = t;
        }
    }
    t = window.open(u, '', 'width=' + w + ',height=' + v + ',' +
        'scrollbars=yes,resizable=yes,toolbar=no,menubar=no,status=no'
    );
    if (t) {
        t.resizeTo(w, v);
        t.focus();
        return false;
    } else {
        return true;
    }
})
JS;
$bookmarklet = preg_replace('/\\b(var|return|typeof) +/', '$1{%space%}', $bookmarklet);
$bookmarklet = preg_replace('/\\s+/', '', $bookmarklet);
$bookmarklet = str_replace('{%space%}', ' ', $bookmarklet);

$bookmarklet_k = $bookmarklet . "('{$url_b}k',240,320,20,-100)";
$bookmarklet_i = $bookmarklet . "('{$url_b}i',320,480,20,-100)";
$bookmarklet_k_ht = p2h($bookmarklet_k);
$bookmarklet_i_ht = p2h($bookmarklet_i);
$bookmarklet_k_en = rawurlencode($bookmarklet_k);
$bookmarklet_i_en = rawurlencode($bookmarklet_i);

$htm['ktai_url'] = <<<EOT
<table border="0" cellspacing="0" cellpadding="1">
    <tbody>
        <tr>
            <th>�g�їpURL:</th>
            <td><a href="{$url_b_ht}k" target="_blank" onclick="return {$bookmarklet_k_ht};">{$url_b_ht}k</a></td>
            <td>[<a href="javascript:{$bookmarklet_k_en};">bookmarklet</a>]</td>
        </tr>
        <tr>
            <th>iPhone�pURL:</th>
            <td><a href="{$url_b_ht}i" target="_blank" onclick="return {$bookmarklet_i_ht};">{$url_b_ht}i</a></td>
            <td>[<a href="javascript:{$bookmarklet_i_en};">bookmarklet</a>]</td>
        </tr>
    </tbody>
</table>
EOT;

// �O��̃��O�C�����
$htm['log'] = '';
$htm['last_login'] = '';
if ($_conf['login_log_rec'] && $_conf['last_login_log_show']) {
    if (($log = P2Util::getLastAccessLog($_conf['login_log_file'])) !== false) {
        $htm['log'] = array_map('p2h', $log);
        $htm['last_login'] = <<<EOT
<br>
<table border="0" cellspacing="0" cellpadding="1">
    <caption>�O��̃��O�C����� - {$htm['log']['date']}</caption>
    <tbody>
        <tr><th>���[�U:</th><td>{$htm['log']['user']}</td></tr>
        <tr><th>IP:</th><td>{$htm['log']['ip']}</td></tr>
        <tr><th>HOST:</th><td>{$htm['log']['host']}</td></tr>
        <tr><th>UA:</th><td>{$htm['log']['ua']}</td></tr>
        <tr><th>REFERER:</th><td>{$htm['log']['referer']}</td></tr>
    </tbody>
</table>
EOT;
    }
}

//=========================================================
// HTML�v�����g
//=========================================================
$ptitle = 'rep2 - title';

echo $_conf['doctype'];
echo <<<EOP
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>{$ptitle}</title>
    <base target="read">
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=title&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
</head>
<body>\n
EOP;

// ��񃁃b�Z�[�W�\��
P2Util::printInfoHtml();

echo <<<EOP
<br>
<div class="container">
    {$newversion_found}
    <p>{$_conf['p2name']} ver.{$_conf['p2version']}<br>
    <a href="{$expack_url_r}"{$_conf['ext_win_target_at']}>{$_conf['expack.web_url']}</a><br>
    <a href="{$p2web_url_r}"{$_conf['ext_win_target_at']}>{$_conf['p2web_url']}</a></p>
    <ul>
        <li><a href="viewtxt.php?file=doc/README.txt">README.txt</a></li>
        <li><a href="viewtxt.php?file=doc/README-EX.txt">README-EX.txt</a></li>
        <li><a href="img/how_to_use.png">�����ȒP�ȑ���@</a></li>
        <li><a href="{$expack_hist_url_r}"{$_conf['ext_win_target_at']}>�g���p�b�N �X�V�L�^</a></li>
        <!-- <li><a href="viewtxt.php?file=doc/ChangeLog.txt">ChangeLog�irep2 �X�V�L�^�j</a></li> -->
    </ul>
    {$htm['auth_user']}
    {$htm['ktai_url']}
    {$htm['last_login']}
</div>
</body>
</html>
EOP;

//==================================================
// �֐�
//==================================================
// {{{ checkUpdatan()

/**
 * �I�����C�����rep2-expack�ŐV�ł��`�F�b�N����
 *
 * @return string HTML
 */
function checkUpdatan()
{
    global $_conf, $p2web_url_r, $expack_url_r, $expack_dl_url_r, $expack_hist_url_r;

    $no_p2status_dl_flag  = false;

    $ver_txt_url = $_conf['expack.web_url'] . 'version.txt';
    $cachefile = P2Util::cacheFileForDL($ver_txt_url);
    FileCtl::mkdirFor($cachefile);

    if (file_exists($cachefile)) {
        // �L���b�V���̍X�V���w�莞�Ԉȓ��Ȃ�
        if (filemtime($cachefile) > time() - $_conf['p2status_dl_interval'] * 86400) {
            $no_p2status_dl_flag = true;
        }
    }

    if (empty($no_p2status_dl_flag)) {
        P2Util::fileDownload($ver_txt_url, $cachefile);
    }

    $ver_txt = FileCtl::file_read_lines($cachefile, FILE_IGNORE_NEW_LINES);
    $update_ver = $ver_txt[0];
    $kita = '�����������i߁�߁j��������!!!!!!';
    //$kita = '��*��ߥ*:.�..�.:*��(߁��)ߥ*:.�. .�.:*��ߥ*!!!!!';

    $newversion_found_html = '';
    if ($update_ver && version_compare($update_ver, $_conf['p2version'], '>')) {
        $newversion_found_html = <<<EOP
<div class="kakomi">
    {$kita}<br>
    �I�����C����� �g���p�b�N �̍ŐV�o�[�W�����������܂����B<br>
    rep2-expack rev.{$update_ver} �� <a href="{$expack_dl_url_r}"{$_conf['ext_win_target_at']}>�_�E�����[�h</a> / <a href="{$expack_hist_url_r}"{$_conf['ext_win_target_at']}>�X�V�L�^</a>
</div>
<hr class="invisible">
EOP;
    }
    return $newversion_found_html;
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