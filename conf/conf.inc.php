<?php
/**
 * rep2 - 基本設定ファイル
 * このファイルは、特に理由の無い限り変更しないこと
 */

// バージョン情報
$_conf = array(
    'p2version' => '1.7.99',        // rep2のバージョン
    'p2expack'  => '120300.9999',   // 拡張パックのバージョン
    'p2name'    => 'expack',        // rep2の名前
    'p2custom'  => '0',             // カスタムバージョン
);

$_conf['p2ua'] = "{$_conf['p2name']}/{$_conf['p2version']}+{$_conf['p2expack']}";

define('P2_VERSION_ID', sprintf('%u', crc32($_conf['p2ua'])));

$_conf['jquery_version'] = '1.7.1';

/*
 * 通常はセッションファイルのロック待ちを極力短くするため
 * ユーザー認証後すぐにセッション変数の変更をコミットする。
 * 認証後もセッション変数を変更するスクリプトでは
 * このファイルを読み込む前に
 *  define('P2_SESSION_CLOSE_AFTER_AUTHENTICATION', 0);
 * とする。
 */
if (!defined('P2_SESSION_CLOSE_AFTER_AUTHENTICATION')) {
    define('P2_SESSION_CLOSE_AFTER_AUTHENTICATION', 1);
}

// {{{ グローバル変数を初期化

$_info_msg_ht = null; // ユーザ通知用 情報メッセージHTML

$MYSTYLE    = array();
$STYLE      = array();
$debug      = false;
$skin       = null;
$skin_en    = null;
$skin_name  = null;
$skin_uniq  = null;
$_login     = null;
$_p2session = null;

$conf_user_def   = array();
$conf_user_rules = array();
$conf_user_rad   = array();
$conf_user_sel   = array();

// }}}

// 基本設定処理を実行
p2_init();

// E_NOTICE および暗黙の配列初期化除け
$_conf['filtering'] = false;
$hd = array('word' => null);
$htm = array();
$word = null;

// {{{ p2_init()

/**
 * 一時変数でグローバル変数を汚染しないように設定処理を関数化
 */
function p2_init()
{
    global $MYSTYLE, $STYLE, $debug;
    global $skin, $skin_en, $skin_name, $skin_uniq;
    global $_conf, $_login, $_p2session;

    // {{{ 基本変数

    $_conf['p2web_url']             = 'http://akid.s17.xrea.com/';
    $_conf['p2ime_url']             = 'http://akid.s17.xrea.com/p2ime.php';
    $_conf['favrank_url']           = 'http://akid.s17.xrea.com/favrank/favrank.php';
    $_conf['expack.web_url']        = 'http://page2.skr.jp/rep2/';
    $_conf['expack.download_url']   = 'http://page2.skr.jp/rep2/downloads.html';
    $_conf['expack.history_url']    = 'http://page2.skr.jp/rep2/history.html';
    $_conf['expack.tgrep_url']      = 'http://page2.xrea.jp/tgrep/search';
    $_conf['expack.gate_php']       = 'http://page2.skr.jp/gate.php';
    $_conf['menu_php']              = 'menu.php';
    $_conf['subject_php']           = 'subject.php';
    $_conf['read_php']              = 'read.php';
    $_conf['read_new_php']          = 'read_new.php';
    $_conf['read_new_k_php']        = 'read_new_k.php';

    // }}}
    // {{{ 環境設定

    // デバッグ
    //$debug = !empty($_GET['debug']);

    putenv('LC_CTYPE=C');

    // タイムゾーンをセット
    date_default_timezone_set('Asia/Tokyo');

    if (!(defined('P2_CLI_RUN') && P2_CLI_RUN)) {
        // スクリプト実行制限時間 (秒)
        set_time_limit(60); // (60)
        // umask
        umask(0);
    }

    // 自動フラッシュをオフにする
    ob_implicit_flush(0);

    // file($filename, FILE_IGNORE_NEW_LINES) で CR/LF/CR+LF のいずれも行末として扱う
    ini_set('auto_detect_line_endings', 1);

    // output_add_rewrite_var(), http_build_query() 等で生成・変更される
    // URLのGETパラメータ区切り文字(列)を"&amp;"にする。（デフォルトは"&"）
    ini_set('arg_separator.output', '&amp;');

    // Windows なら
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        define('P2_OS_WINDOWS', 1);
    } else {
        define('P2_OS_WINDOWS', 0);
    }

    // HTTPS接続なら
    if (array_key_exists('HTTPS', $_SERVER) && strcasecmp($_SERVER['HTTPS'], 'on') === 0) {
        define('P2_HTTPS_CONNECTION', 1);
    } else {
        define('P2_HTTPS_CONNECTION', 0);
    }

    // ヌルバイト定数
    // mbstring.script_encoding = SJIS-win だと
    // "\0", "\x00" 以降がカットされるので、chr()関数を使う
    define('P2_NULLBYTE', chr(0));

    // }}}
    // {{{ P2Util::header_content_type() を不要にするおまじない

    ini_set('default_mimetype', 'text/html');
    ini_set('default_charset', 'Shift_JIS');

    // }}}
    // {{{ ライブラリ類のパス設定

    define('P2_CONFIG_DIR', __DIR__);

    define('P2_BASE_DIR', dirname(P2_CONFIG_DIR));

    // ドキュメントルート
    define('P2_WWW_DIR', P2_BASE_DIR . '/rep2');

    // コマンドラインスクリプト
    define('P2_SCRIPT_DIR', P2_BASE_DIR . '/scripts');

    // 基本的な機能を提供するするライブラリ
    define('P2_LIB_DIR', P2_BASE_DIR . '/lib');

    // おまけ的な機能を提供するするライブラリ
    define('P2EX_LIB_DIR', P2_BASE_DIR . '/lib/expack');

    // プラグインライブラリ
    define('P2_PLUGIN_DIR', P2_BASE_DIR . '/lib/plugins');

    // スタイルシート
    define('P2_STYLE_DIR', P2_BASE_DIR . '/style');

    // スキン
    define('P2_SKIN_DIR', P2_WWW_DIR . '/skin');
    define('P2_USER_SKIN_DIR', P2_WWW_DIR . '/user_skin');

    // }}}
    // {{{ 環境チェックとデバッグ

    // ユーティリティを読み込む
    include P2_LIB_DIR . '/global.funcs.php';
    include P2_LIB_DIR . '/startup.funcs.php';
    spl_autoload_register('p2_load_class');

    require_once P2_LIB_DIR . '/wiki/P2UtilWiki.php';

    // 動作環境を確認 (要件を満たしているならコメントアウト可)
    p2_check_environment();

    if ($debug) {
        if (!class_exists('Benchmark_Profiler', false)) {
            require 'Benchmark/Profiler.php';
        }
        $profiler = new Benchmark_Profiler(true);
        // p2_print_memory_usage();
        register_shutdown_function('p2_print_memory_usage');
    }

    // }}}
    // {{{ 文字コードの指定

    mb_internal_encoding('SJIS-win');
    mb_http_output('pass');
    mb_substitute_character(63); // 文字コード変換に失敗した文字が "?" になる
    //mb_substitute_character(0x3013); // 〓
    //ob_start('mb_output_handler');

    if (function_exists('mb_ereg_replace')) {
        define('P2_MBREGEX_AVAILABLE', 1);
        mb_regex_encoding('SJIS-win');
    } else {
        define('P2_MBREGEX_AVAILABLE', 0);
    }

    // }}}
    // {{{ 管理者用設定etc.

    // 管理者用設定を読み込み
    include P2_CONFIG_DIR . '/conf_admin.inc.php';

    // ディレクトリの絶対パス化
    $_conf['data_dir'] = p2_realpath($_conf['data_dir']);
    $_conf['dat_dir']  = p2_realpath($_conf['dat_dir']);
    $_conf['idx_dir']  = p2_realpath($_conf['idx_dir']);
    $_conf['pref_dir'] = p2_realpath($_conf['pref_dir']);
    $_conf['db_dir']   = p2_realpath($_conf['db_dir']);

    // 管理用保存ディレクトリ
    $_conf['admin_dir'] = $_conf['data_dir'] . '/admin';

    // cache 保存ディレクトリ
    // 2005/06/29 $_conf['pref_dir'] . '/p2_cache' より変更
    $_conf['cache_dir'] = $_conf['data_dir'] . '/cache';

    // Cookie 保存ディレクトリ
    // 2008/09/09 $_conf['pref_dir'] . '/p2_cookie' より変更
    $_conf['cookie_dir'] = $_conf['data_dir'] . '/cookie';

    // コンパイルされたテンプレートの保存ディレクトリ
    $_conf['compile_dir'] = $_conf['data_dir'] . '/compile';

    // セッションデータ保存ディレクトリ
    $_conf['session_dir'] = $_conf['data_dir'] . '/session';

    // テンポラリディレクトリ
    $_conf['tmp_dir'] = $_conf['data_dir'] . '/tmp';

    // バージョンIDを二重引用符やヒアドキュメント内に埋め込むための変数
    $_conf['p2_version_id'] = P2_VERSION_ID;

    // 文字コード自動判定用のヒント文字列
    $_conf['detect_hint'] = '◎◇';
    $_conf['detect_hint_input_ht'] = '<input type="hidden" name="_hint" value="◎◇">';
    $_conf['detect_hint_input_xht'] = '<input type="hidden" name="_hint" value="◎◇" />';
    //$_conf['detect_hint_utf8'] = mb_convert_encoding('◎◇', 'UTF-8', 'SJIS-win');
    $_conf['detect_hint_q'] = '_hint=%81%9D%81%9E'; // rawurlencode($_conf['detect_hint'])
    $_conf['detect_hint_q_utf8'] = '_hint=%E2%97%8E%E2%97%87'; // rawurlencode($_conf['detect_hint_utf8'])

    // }}}
    // {{{ 変数設定

    $preferences = array(
        'conf_user_file'    => 'conf_user.srd.cgi',     // ユーザー設定ファイル (シリアライズドデータ)
        'favita_brd'        => 'p2_favita.brd',         // お気に板 (brd)
        'favlist_idx'       => 'p2_favlist.idx',        // お気にスレ (idx)
        'recent_idx'        => 'p2_recent.idx',         // 最近読んだスレ (idx)
        'palace_idx'        => 'p2_palace.idx',         // スレの殿堂 (idx)
        'res_hist_idx'      => 'p2_res_hist.idx',       // 書き込みログ (idx)
        'res_hist_dat'      => 'p2_res_hist.dat',       // 書き込みログファイル (dat)
        'res_hist_dat_php'  => 'p2_res_hist.dat.php',   // 書き込みログファイル (データPHP)
        'idpw2ch_php'       => 'p2_idpw2ch.php',        // 2ch ID認証設定ファイル (データPHP)
        'sid2ch_php'        => 'p2_sid2ch.php',         // 2ch ID認証セッションID記録ファイル (データPHP)
        'auth_user_file'    => 'p2_auth_user.php',      // 認証ユーザ設定ファイル(データPHP)
        'login_log_file'    => 'p2_login.log.php',      // ログイン履歴 (データPHP)
        'login_failed_log_file' => 'p2_login_failed.dat.php',   // ログイン失敗履歴 (データPHP)
    );
    foreach ($preferences as $k => $v) {
        $_conf[$k] = $_conf['pref_dir'] . '/' . $v;
    }

    $_conf['orig_favita_brd']   = $_conf['favita_brd'];
    $_conf['orig_favlist_idx']  = $_conf['favlist_idx'];

    $_conf['cookie_db_path']    = $_conf['db_dir'] . '/p2_cookies.sqlite3';
    $_conf['post_db_path']      = $_conf['db_dir'] . '/p2_post_data.sqlite3';
    $_conf['hostcheck_db_path'] = $_conf['db_dir'] . '/p2_hostcheck_cache.sqlite3';
    $_conf['matome_db_path']    = $_conf['db_dir'] . '/p2_matome_cache.sqlite3';
    $_conf['iv2_cache_db_path'] = $_conf['db_dir'] . '/iv2_cache.sqlite3';

    // 補正
    if ($_conf['expack.use_pecl_http'] && !extension_loaded('http')) {
        if (!($_conf['expack.use_pecl_http'] == 2 && $_conf['expack.dl_pecl_http'])) {
            $_conf['expack.use_pecl_http'] = 0;
        }
    }

    // }}}

    include P2_CONFIG_DIR . '/empty_style.php';
    include P2_LIB_DIR . '/bootstrap.php';
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
