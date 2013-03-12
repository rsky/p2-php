<?php
/**
 * ImageCache2 - エラーログ・ブラックリスト閲覧
 */

// {{{ p2基本設定読み込み&認証

define('P2_OUTPUT_XHTML', 1);

require_once __DIR__ . '/../init.php';

$_login->authorize();

if (!$_conf['expack.ic2.enabled']) {
    p2die('ImageCache2は無効です。', 'conf/conf_admin_ex.inc.php の設定を変えてください。');
}

// }}}
// {{{ 初期化

// ライブラリ読み込み
require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';

// }}}
// {{{ 設定と消去

// 設定ファイル読み込み
$ini = ic2_loadconfig();

if (!isset($_REQUEST['table'])) {
    p2die('ImageCache2 - 不正なクエリ');
}

$mode = $_REQUEST['table'];
switch ($mode) {
    case 'errlog':
        $table = new ImageCache2_DataObject_Errors();
        $table->orderBy('occured ASC');
        $title = 'エラーログ';
        break;
    case 'blacklist':
        $table = new ImageCache2_DataObject_BlackList();
        $table->orderBy('uri ASC');
        $title = 'ブラックリスト';
        break;
    default:
        p2die('ImageCache2 - 不正なクエリ');
}

$db = $table->getDatabaseConnection();
if (isset($_POST['clean'])) {
    $sql = 'DELETE FROM ' . $db->quoteIdentifier($table->__table);
    $result = $db->query($sql);
    if (DB::isError($result)) {
        p2die($result->getMessage());
    }
} elseif (isset($_POST['delete']) && isset($_POST['target']) && is_array($_POST['target'])) {
    foreach ($_POST['target'] as $target) {
        $delete = clone $table;
        $delete->uri = $target;
        $delete->delete();
    }
}

// }}}
// {{{ 出力

$_flexy_options = array(
    'locale' => 'ja',
    'charset' => 'Shift_JIS',
    'compileDir' => $_conf['compile_dir'] . DIRECTORY_SEPARATOR . 'ic2',
    'templateDir' => P2EX_LIB_DIR . '/ImageCache2/templates',
    'numberFormat' => '', // ",0,'.',','" と等価
);

$flexy = new HTML_Template_Flexy($_flexy_options);

$flexy->setData('php_self', $_SERVER['SCRIPT_NAME']);
$flexy->setData('p2vid', P2_VERSION_ID);
$flexy->setData('skin', $skin_en);
$flexy->setData('title', $title);
$flexy->setData('mode', $mode);
$flexy->setData('reload_js', $_SERVER['SCRIPT_NAME'] . '?nt=' . time() . '&table=' . $mode);
$flexy->setData('info_msg', P2Util::getInfoHtml());
$flexy->setData('pc', !$_conf['ktai']);
$flexy->setData('iphone', $_conf['iphone']);
$flexy->setData('doctype', $_conf['doctype']);
$flexy->setData('extra_headers',   $_conf['extra_headers_ht']);
$flexy->setData('extra_headers_x', $_conf['extra_headers_xht']);

if ($table->find()) {
    switch ($mode) {
        case 'errlog':
            $flexy->setData('data_renderer_errlog', true);
            $flexy->setData('data', ic2_dump_table_errlog($table));
            break;
        case 'blacklist':
            $flexy->setData('data_renderer_blacklist', true);
            $flexy->setData('data', ic2_dump_table_blacklist($table));
            break;
    }
}

P2Util::header_nocache();
$flexy->compile('ic2vt.tpl.html');
$flexy->output();

// }}}
// {{{ 関数
// {{{ ic2_dump_table_errlog()

/**
 * エラーログを取得する
 */
function ic2_dump_table_errlog($dbdo)
{
    $data = array();
    while ($dbdo->fetch()) {
        $obj = new stdClass();
        $obj->uri = $dbdo->uri;
        $obj->date = date('Y-m-d (D) H:i:s', $dbdo->occured);
        $obj->code = $dbdo->errcode;
        $obj->message = mb_convert_encoding($dbdo->errmsg, 'CP932', 'UTF-8');
        $data[] = $obj;
    }
    return $data;
}

// }}}
// {{{ ic2_dump_table_blacklist()

/**
 * ブラックリストを取得する
 */
function ic2_dump_table_blacklist($dbdo)
{
    $data = array();
    while ($dbdo->fetch()) {
        $obj = new stdClass();
        $obj->uri = $dbdo->uri;
        switch ($dbdo->type) {
            case '0':
                $obj->type = 'お腹いっぱい';
                break;
            case '1':
                $obj->type = 'あぼーん';
                break;
            case '2':
                $obj->type = 'ウィルス感染';
                break;
            default:
                $type = '???';
        }
        $obj->size = $dbdo->size;
        $obj->md5 = $dbdo->md5;
        $data[] = $obj;
    }
    return $data;
}

// }}}
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
