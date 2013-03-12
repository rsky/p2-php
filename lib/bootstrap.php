<?php
/**
 * rep2expack - 初期化スクリプト
 * conf/conf.inc.php の p2_init() から読み込まれる。
 */

p2_rewrite_vars_for_proxy();

// {{{ ユーザー設定 読込

// ユーザー設定があれば読み込む
if (file_exists($_conf['conf_user_file'])) {
    if ($cont = file_get_contents($_conf['conf_user_file'])) {
        $conf_user = unserialize($cont);
    } else {
        $conf_user = null;
    }

    // 何らかの理由でユーザー設定ファイルが壊れていたら
    if (!is_array($conf_user)) {
        if (defined('P2_CLI_RUN')) {
            p2die("ユーザー設定ファイル {$dispname} が読み込めなかったか、壊れています。");
        } elseif (unlink($_conf['conf_user_file'])) {
            P2Util::pushInfoHtml('<p>ユーザー設定ファイルが壊れていたので削除しました。</p>');
            $conf_user = array();
        } else {
            $dispname = '$_conf[\'pref_dir\']/' . basename($_conf['conf_user_file']);
            p2die("壊れているユーザー設定ファイル {$dispname} を削除できませんでした。");
        }
    }

    // ユーザー設定のバージョンをチェック
    if (array_key_exists('.', $conf_user) &&
        preg_match('/^\\d{6}\\.\\d{4}$/', $conf_user['.']))
    {
        $config_version = $conf_user['.'];
    } else {
        $config_version = '000000.0000';
    }

    if ($config_version !== $_conf['p2version'] && !defined('P2_CLI_RUN')) {
        // デフォルト設定を読み込み、ユーザー設定とともにマージ
        include P2_CONFIG_DIR . '/conf_user_def.inc.php';
        $_conf = array_merge($_conf, $conf_user_def, $conf_user);
        $save_conf_user = true;
    } else {
        // キャッシュされていたユーザー設定をマージ
        $_conf = array_merge($_conf, $conf_user);
        $save_conf_user = false;
    }
} else {
    // デフォルト設定を読み込み、マージ
    include P2_CONFIG_DIR . '/conf_user_def.inc.php';
    $_conf = array_merge($_conf, $conf_user_def);
    $save_conf_user = true;
}

// コマンドラインモードではここまで
if (defined('P2_CLI_RUN')) {
    return;
}

// 新しいユーザー設定をシリアライズして保存
if ($save_conf_user) {
    $conf_save = array('.' => $_conf['p2version']);
    foreach ($conf_user_def as $k => $v) {
        $conf_save[$k] = $_conf[$k];
    }

    $cont = serialize($conf_save);
    if (FileCtl::file_write_contents($_conf['conf_user_file'], $cont) === false) {
        $dispname = '$_conf[\'pref_dir\']/' . basename($_conf['conf_user_file']);
        p2die("ユーザー設定ファイル {$dispname} に書き込めませんでした。");
    }
}

// }}}
// {{{ リクエスト変数の検証と文字コード変換

/**
 * 日本語を入力する可能性のあるフォームには隠し要素で
 * エンコーディング判定用の文字列を仕込んでいる
 *
 * $_COOKIE は $_REQUEST に含めない
 */
if (!empty($_GET) || !empty($_POST)) {
    $hint = null;

    // NULLバイトアタックとスクリプトインジェクションの検証、
    // エンコーディング判定用文字列の取得
    if (!empty($_POST)) {
        // 新規ログインとメンバーログインの同時指定はありえないので、エラーを出す
        if (isset($_POST['submit_new']) && isset($_POST['submit_member'])) {
            p2die('無効なリクエストです。');
        }

        array_walk_recursive($_POST, 'p2_scan_nullbyte');
        p2_scan_script_injection($_POST);
        if (array_key_exists('_hint', $_POST)) {
            $hint = $_POST['_hint'];
        }
    }
    if (!empty($_GET)) {
        array_walk_recursive($_GET, 'p2_scan_nullbyte');
        p2_scan_script_injection($_GET);
        if (array_key_exists('_hint', $_GET)) {
            $hint = $_GET['_hint'];
        }
    }

    // エンコーディング判定
    if ($hint) {
        $request_encoding = mb_detect_encoding($hint, 'ASCII,UTF-8,SJIS-win');
        if ($request_encoding === 'ASCII') {
            p2die('不正なエンコーディング判定ヒントです。');
        }
    } else {
        $request_encoding = null;
    }

    // UTF-8ならShift_JISに変換
    if ($request_encoding === 'UTF-8') {
        mb_convert_variables('SJIS-win', 'UTF-8', $_GET, $_POST);
    }

    // $_REQUEST を再構成
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $_REQUEST = array_merge($_GET, $_POST);
    } else {
        $_REQUEST = $_GET;
    }
} else {
    $_REQUEST = array();
}

// }}}
// {{{ 端末判定

$_conf['ktai'] = false;
$_conf['iphone'] = false;
$_conf['input_type_search'] = false;

$_conf['accesskey'] = 'accesskey';
$_conf['accept_charset'] = 'Shift_JIS';
$_conf['extra_headers_ht'] = '';

$_conf['use_cookies'] = true;

$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
$mobile = Net_UserAgent_Mobile::singleton($userAgent);

// iPhone, iPod Touch or Android
if (UA::isIPhoneGroup($userAgent)) {
    $_conf['ktai'] = true;
    $_conf['iphone'] = true;
    $_conf['input_type_search'] = true;
    $_conf['accept_charset'] = 'UTF-8';

// PC等
} elseif ($mobile->isNonMobile()) {
    // Safari
    if (UA::isSafariGroup($userAgent)) {
        $_conf['input_type_search'] = true;
        $_conf['accept_charset'] = 'UTF-8';

    // Windows Mobile
    } elseif (P2Util::isClientOSWindowsCE()) {
        $_conf['ktai'] = true;

    // 携帯ゲーム機
    } elseif (UA::isNintendoDS($userAgent) || UA::isPSP($userAgent)) {
        $_conf['ktai'] = true;
    }

// 携帯
} else {
    $_conf['ktai'] = true;

    // NTT docomo iモード
    if ($mobile->isDoCoMo()) {
        // iモードブラウザ2.0からCookieに対応している
        $_conf['use_cookies'] = UA::isIModeBrowser2();

    // au EZweb
    //} elseif ($mobile->isEZweb()) {
    //    $_conf['use_cookies'] = true;

    // SoftBank Mobile
    } elseif ($mobile->isSoftBank()) {
        // 3GC型端末はnonumber属性をサポートしないのでaccesskeyを使う
        if (!$mobile->isType3GC()) {
            $_conf['accesskey'] = 'DIRECTKEY';
            // 3GC型端末とW型端末以外はCookieをサポートしない
            if (!$mobile->isTypeW()) {
                $_conf['use_cookies'] = false;
            }
        }

    // WILLCOM AIR-EDGE
    //} elseif ($mobile->isWillcom()) {
    //    $_conf['use_cookies'] = true;

    // その他
    //} else {
    //    $_conf['use_cookies'] = true;
    }
}

// }}}
// {{{ クエリーによる強制ビュー指定

// b=pc はまだリンク先が完全でない?
// b=i はCSSでWebKitの独自拡張/先行実装プロパティを多用している

$_conf['b'] = $_conf['client_type'] = ($_conf['iphone'] ? 'i' : ($_conf['ktai'] ? 'k' : 'pc'));
$_conf['view_forced_by_query'] = false;
$_conf['k_at_a'] = '';
$_conf['k_at_q'] = '';
$_conf['k_input_ht'] = '';

if (isset($_REQUEST['b'])) {
    switch ($_REQUEST['b']) {

    // 強制PCビュー指定
    case 'pc':
        if ($_conf['b'] != 'pc') {
            $_conf['b'] = 'pc';
            $_conf['ktai'] = false;
            $_conf['iphone'] = false;
        }
        break;

    // 強制iPhoneビュー指定
    case 'i':
        if ($_conf['b'] != 'i') {
            $_conf['b'] = 'i';
            $_conf['ktai'] = true;
            $_conf['iphone'] = true;
        }
        break;

    // 強制携帯ビュー指定
    case 'k':
        if ($_conf['b'] != 'k') {
            $_conf['b'] = 'k';
            $_conf['ktai'] = true;
            $_conf['iphone'] = false;
        }
        break;

    } // endswitch

    // 強制ビュー指定されていたなら
    if ($_conf['b'] != $_conf['client_type']) {
        $_conf['view_forced_by_query'] = true;
        $_conf['k_at_a'] = '&amp;b=' . $_conf['b'];
        $_conf['k_at_q'] = '?b=' . $_conf['b'];
        $_conf['k_input_ht'] = '<input type="hidden" name="b" value="' . $_conf['b'] . '">';
        //output_add_rewrite_var('b', $_conf['b']);
    }
}

// }}}
// {{{ ユーザー設定の調整処理

$_conf['ext_win_target_at'] = ($_conf['ext_win_target']) ? " target=\"{$_conf['ext_win_target']}\"" : '';
$_conf['bbs_win_target_at'] = ($_conf['bbs_win_target']) ? " target=\"{$_conf['bbs_win_target']}\"" : '';

if ($_conf['get_new_res']) {
    if ($_conf['get_new_res'] == 'all') {
        $_conf['get_new_res_l'] = $_conf['get_new_res'];
    } else {
        $_conf['get_new_res_l'] = 'l' . $_conf['get_new_res'];
    }
} else {
    $_conf['get_new_res_l'] = 'l200';
}

if ($_conf['expack.user_agent']) {
    ini_set('user_agent', $_conf['expack.user_agent']);
}

// }}}
// {{{ デザイン設定 読込

$skin_name = $default_skin_name = 'conf_user_style';
$skin = P2_CONFIG_DIR . '/conf_user_style.inc.php';
if (!$_conf['ktai'] && $_conf['expack.skin.enabled']) {
    // 保存されているスキン名
    $saved_skin_name = null;
    if (file_exists($_conf['expack.skin.setting_path'])) {
        $saved_skin_name = rtrim(file_get_contents($_conf['expack.skin.setting_path']));
        if (!preg_match('/^[0-9A-Za-z_\\-]+$/', $saved_skin_name)) {
            $saved_skin_name = null;
        }
    } else {
        FileCtl::make_datafile($_conf['expack.skin.setting_path']);
    }

    // リクエストで指定されたスキン名
    $new_skin_name = null;
    if (array_key_exists('skin', $_REQUEST) && is_string($_REQUEST['skin'])) {
        $new_skin_name = $_REQUEST['skin'];
        if (!preg_match('/^[0-9A-Za-z_\\-]+$/', $new_skin_name)) {
            $new_skin_name = null;
        } elseif ($new_skin_name != $saved_skin_name) {
            FileCtl::file_write_contents($_conf['expack.skin.setting_path'], $new_skin_name);
        }
    }

    // リクエストで指定された一時スキン名
    $tmp_skin_name = null;
    if (array_key_exists('tmp_skin', $_REQUEST) && is_string($_REQUEST['tmp_skin'])) {
        $tmp_skin_name = $_REQUEST['tmp_skin'];
        if (!preg_match('/^[0-9A-Za-z_\\-]+$/', $tmp_skin_name)) {
            $tmp_skin_name = null;
        }
    }

    // スキン検索
    foreach (array($tmp_skin_name, $new_skin_name, $saved_skin_name, $default_skin_name) as $skin_name) {
        if ($skin_name !== null) {
            if ($skin_name == $default_skin_name) {
                break;
            }
            $user_skin_path = P2_USER_SKIN_DIR . '/' . $skin_name . '.php';
            if (file_exists($user_skin_path)) {
                $skin = $user_skin_path;
                break;
            }
            $bundled_skin_path = P2_SKIN_DIR . '/' . $skin_name . '.php';
            if (file_exists($bundled_skin_path)) {
                $skin = $bundled_skin_path;
                break;
            }
        }
    }
}

if (!file_exists($skin)) {
    $skin_name = 'conf_user_style';
    $skin = P2_CONFIG_DIR . '/conf_user_style.inc.php';
}
$skin_en = rawurlencode($skin_name) . '&amp;_=' . P2_VERSION_ID;
if ($_conf['view_forced_by_query']) {
    $skin_en .= $_conf['k_at_a'];
}

// デフォルト設定を読み込んで
include P2_CONFIG_DIR . '/conf_user_style.inc.php';
// スキンで上書き
if ($skin != P2_CONFIG_DIR . '/conf_user_style.inc.php') {
    include $skin;
}

// }}}
// {{{ デザイン設定の調整処理

$skin_uniq = P2_VERSION_ID;

foreach ($STYLE as $K => $V) {
    if ($V === null || $V === '') {
        // pass
    } elseif (strpos($K, 'fontfamily') !== false) {
        $STYLE[$K] = p2_correct_css_fontfamily($V);
    } elseif (strpos($K, 'color') !== false) {
        $STYLE[$K] = p2_correct_css_color($V);
    } elseif (strpos($K, 'background') !== false) {
        $STYLE[$K] = "url('" . p2_escape_css_url($V) . "')";
    }
}

if (!$_conf['ktai']) {
    if (!function_exists('p2_fontconfig_apply_custom')) {
        include P2_LIB_DIR . '/fontconfig.inc.php';
    }

    if ($_conf['expack.am.enabled']) {
        $_conf['expack.am.fontfamily'] = p2_correct_css_fontfamily($_conf['expack.am.fontfamily']);
        if ($STYLE['fontfamily']) {
            $_conf['expack.am.fontfamily'] .= '","' . $STYLE['fontfamily'];
        }
    }

    p2_fontconfig_apply_custom();
}

// }}}
// {{{ 携帯・iPhone用変数

// iPhone用HTMLヘッダ要素
if ($_conf['client_type'] == 'i') {
    $_conf['touch_icon_ht'] = <<<EOS
<link rel="apple-touch-icon" type="image/png" href="img/touch-icon/p2-serif.png" />
EOS;
//<link rel="apple-touch-icon-precomposed" href="data:image/png;base64,iVBORw0K..." />

    switch ($_conf['b']) {

    // 強制PCビュー時
    case 'pc':
        $_conf['extra_headers_ht'] .= <<<EOS
<meta name="format-detection" content="telephone=no">
{$_conf['touch_icon_ht']}
<style type="text/css">body { -webkit-text-size-adjust: none; }</style>
EOS;
        break;

    // 強制携帯ビュー時
    case 'k':
        $_conf['extra_headers_ht'] .= <<<EOS
<meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=yes">
<meta name="format-detection" content="telephone=no">
{$_conf['touch_icon_ht']}
<style type="text/css">
body { word-break: normal; word-break: break-all; -webkit-text-size-adjust: none; }
* { font-family: sans-serif; font-size: medium; line-height: 150%; }
h1 { font-size: xx-large; }
h2 { font-size: x-large; }
h3 { font-size: large; }
</style>
EOS;
        break;

    // 純正iPhoneビュー
    case 'i':
    default:
        $_conf['extra_headers_ht'] .= <<<EOS
<meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=yes">
<meta name="format-detection" content="telephone=no">
{$_conf['touch_icon_ht']}
<link rel="stylesheet" type="text/css" media="screen" href="css/iphone.css?{$_conf['p2_version_id']}">
<script type="text/javascript" src="js/iphone.js?{$_conf['p2_version_id']}"></script>
EOS;

    } // endswitch

// 強制iPhoneビュー時
} elseif ($_conf['iphone']) {
    $_conf['extra_headers_ht'] .= <<<EOS
<link rel="stylesheet" type="text/css" media="screen" href="css/iphone.css?{$_conf['p2_version_id']}">
<script type="text/javascript" src="js/iphone.js?{$_conf['p2_version_id']}"></script>
EOS;
}

// AndroidではJavaScript-XPathを使う
if ($_conf['iphone'] && UA::isAndroidWebKit()) {
    $_conf['extra_headers_ht'] .= <<<EOS
<script type="text/javascript">window.jsxpath = { 'useNative': false };</script>
<script type="text/javascript" src="js/javascript-xpath.js?{$_conf['p2_version_id']}"></script>
EOS;
}

// iPhone用スキン
if ($_conf['iphone'] && isset($_conf['expack.iphone.skin'])) {
    if (strpos($_conf['expack.iphone.skin'], DIRECTORY_SEPARATOR) === false) {
        $iskin = 'skin/iphone/' . $iskin . '.css';
        if (file_exists($iskin)) {
            $iskin_mtime = filemtime($iskin);
            $_conf['extra_headers_ht'] .= <<<EOS
<link rel="stylesheet" type="text/css" media="screen" href="{$iskin}?{$iskin_mtime}">
EOS;
        }
    }
}

// 携帯用「トップに戻る」リンクとaccesskey

// デフォルト値
$accesskeys = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '#');
$_conf['k_accesskey_at'] = array();
foreach ($accesskeys as $key) {
    $_conf['k_accesskey_at'][$key] = " accesskey=\"{$key}\"";
}
$_conf['k_accesskey_st'] = array_combine($accesskeys, array_fill(0, count($accesskeys), ''));

if ($_conf['ktai']) {
    // iPhone
    if ($_conf['iphone']) {
        // 強制iPhoneビュー以外はaccesskeyを使わない
        if ($_conf['client_type'] == 'i') {
            $_conf['k_accesskey_at'] = $_conf['k_accesskey_st'];
        }
        $_conf['k_to_index_ht'] = <<<EOP
<a href="index.php{$_conf['k_at_q']}" class="button"{$_conf['k_accesskey_at'][0]}>TOP</a>
EOP;

    // その他
    } else {
        // SoftBank Mobile 旧型端末
        if ($_conf['accesskey'] == 'DIRECTKEY') {
            $_conf['k_accesskey_at'] = array();
            foreach ($accesskeys as $key) {
                $_conf['k_accesskey_at'][$key] = " directkey=\"{$key}\" nonumber";
            }
        }

        switch ($_conf['mobile.display_accesskey']) {
        case 2:
            if (!function_exists('p2_get_emoji')) {
                include P2_LIB_DIR . '/emoji.inc.php';
            }
            $emoji = p2_get_emoji($mobile);
            //$emoji = p2_get_emoji(Net_UserAgent_Mobile::factory('KDDI-SA31 UP.Browser/6.2.0.7.3.129 (GUI) MMP/2.0'));
            $_conf['k_accesskey_st'] = array();
            foreach ($accesskeys as $key) {
                $_conf['k_accesskey_st'][$key] = $emoji[$key];
            }
            break;
        case 0:
            break;
        case 1:
        default:
            $_conf['k_accesskey_st'] = array();
            foreach ($accesskeys as $key) {
                $_conf['k_accesskey_st'][$key] = $key . '.';
            }
        }

        $_conf['k_to_index_ht'] = <<<EOP
<a href="index.php{$_conf['k_at_q']}"{$_conf['k_accesskey_at'][0]}>{$_conf['k_accesskey_st'][0]}TOP</a>
EOP;
    }

    foreach ($_conf['k_accesskey'] as $name => $key) {
        $_conf['k_accesskey_at'][$name] = $_conf['k_accesskey_at'][$key];
        $_conf['k_accesskey_st'][$name] = $_conf['k_accesskey_st'][$key];
    }
}

// }}}
// {{{ 携帯用カラーリングの調整処理

$_conf['k_colors'] = '';

if ($_conf['ktai']) {
    // 基本色
    if (!$_conf['iphone']) {
        if ($_conf['mobile.background_color']) {
            $_conf['k_colors'] .= ' bgcolor="' . p2h($_conf['mobile.background_color']) . '"';
        }
        if ($_conf['mobile.text_color']) {
            $_conf['k_colors'] .= ' text="' . p2h($_conf['mobile.text_color']) . '"';
        }
        if ($_conf['mobile.link_color']) {
            $_conf['k_colors'] .= ' link="' . p2h($_conf['mobile.link_color']) . '"';
        }
        if ($_conf['mobile.vlink_color']) {
            $_conf['k_colors'] .= ' vlink="' . p2h($_conf['mobile.vlink_color']) . '"';
        }
    }

    // 文字色
    if ($_conf['mobile.newthre_color']) {
        $STYLE['mobile_subject_newthre_color'] = p2h($_conf['mobile.newthre_color']);
    }
    if ($_conf['mobile.newres_color']) {
        $STYLE['mobile_read_newres_color']    = p2h($_conf['mobile.newres_color']);
        $STYLE['mobile_subject_newres_color'] = p2h($_conf['mobile.newres_color']);
    }
    if ($_conf['mobile.ttitle_color']) {
        $STYLE['mobile_read_ttitle_color'] = p2h($_conf['mobile.ttitle_color']);
    }
    if ($_conf['mobile.ngword_color']) {
        $STYLE['mobile_read_ngword_color'] = p2h($_conf['mobile.ngword_color']);
    }
    if ($_conf['mobile.onthefly_color']) {
        $STYLE['mobile_read_onthefly_color'] = p2h($_conf['mobile.onthefly_color']);
    }

    // マーカー
    if ($_conf['mobile.match_color']) {
        if ($_conf['iphone']) {
            $_conf['extra_headers_ht'] .= sprintf('<style type="text/css">b.filtering, span.matched { color: %s; }</style>',
                                                  p2h($_conf['mobile.match_color']));
            $_conf['k_filter_marker'] = '<span class="matched">\\1</span>';
        } else {
            $_conf['k_filter_marker'] = '<font color="' . p2h($_conf['mobile.match_color']) . '">\\1</font>';
        }
    } else {
        $_conf['k_filter_marker'] = false;
    }
}

// }}}
// {{{ セッション

ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
session_set_cookie_params(0); // session.cookie_lifetime = 0
session_name('PS');

// {{{ セッションデータ保存ディレクトリをチェック

if ($_conf['session_save'] == 'p2' and session_module_name() == 'files') {
    if (!is_dir($_conf['session_dir'])) {
        FileCtl::mkdirRecursive($_conf['session_dir']);
    } elseif (!is_writable($_conf['session_dir'])) {
        p2die("セッションデータ保存ディレクトリ ({$_conf['session_dir']}) に書き込み権限がありません。");
    }

    session_save_path($_conf['session_dir']);
}

// }}}

$_p2session = new Session(null, null, $_conf['use_cookies']);

// }}}
// {{{ お気にセット

// 複数のお気にセットを使うとき
if ($_conf['expack.misc.multi_favs']) {
    // 切り替え表示用に全てのお気にスレ・お気に板を読み込んでおく
    FavSetManager::loadAllFavSet();
    // お気にセットを切り替える
    FavSetManager::switchFavSet();
} else {
    $_conf['m_favlist_set'] = '';
    $_conf['m_favlist_set_at_a'] = '';
    $_conf['m_favlist_set_input_ht'] = '';
    $_conf['m_favita_set'] = '';
    $_conf['m_favita_set_at_a'] = '';
    $_conf['m_favita_set_input_ht'] = '';
    $_conf['m_rss_set'] = '';
    $_conf['m_rss_set_at_a'] = '';
    $_conf['m_rss_set_input_ht'] = '';
}

// }}}
// {{{ misc.

// DOCTYPE HTML 宣言
$_conf['doctype'] = '';
if (!$_conf['ktai'] || $_conf['client_type'] != 'k') {
    $_conf['doctype'] = "<!DOCTYPE html>\n";
}

// XHTMLヘッダ要素
if (defined('P2_OUTPUT_XHTML')) {
    $_conf['extra_headers_xht'] = preg_replace('/<((?:link|meta) .+?)>/', '<\\1 />', $_conf['extra_headers_ht']);
}

// ログインクラスのインスタンス生成（ログインユーザが指定されていなければ、この時点でログインフォーム表示に）
$_login = new Login();

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
