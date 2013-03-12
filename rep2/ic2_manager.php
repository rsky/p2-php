<?php
/**
 * ImageCache2 - メンテナンス
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

// 設定読み込み
$ini = ic2_loadconfig();
if ($ini['Viewer']['cache'] && file_exists($_conf['iv2_cache_db_path'])) {
    $viewer_cache_exists = true;
} else {
    $viewer_cache_exists = false;
}

// データベースに接続
$db = DB::connect($ini['General']['dsn']);
if (DB::isError($db)) {
    p2die($db->getMessage());
}

// テンプレートエンジン初期化
$_flexy_options = array(
    'locale' => 'ja',
    'charset' => 'Shift_JIS',
    'compileDir' => $_conf['compile_dir'] . DIRECTORY_SEPARATOR . 'ic2',
    'templateDir' => P2EX_LIB_DIR . '/ImageCache2/templates',
    'numberFormat' => '', // ",0,'.',','" と等価
);

$flexy = new HTML_Template_Flexy($_flexy_options);

// }}}
// {{{ データベース操作・ファイル削除

if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        // 画像を削除する
        case 'dropZero':
        case 'dropAborn':
            if ($_POST['action'] == 'dropZero') {
                // ランク=0 の画像を削除する
                $where = $db->quoteIdentifier('rank') . ' = 0';
                if (isset($_POST['dropZeroLimit'])) {
                    // 取得した期間を限定
                    switch ($_POST['dropZeroSelectTime']) {
                        case '24hours': $expires = 86400; break;
                        case 'aday':    $expires = 86400; break;
                        case 'aweek':   $expires = 86400 * 7; break;
                        case 'amonth':  $expires = 86400 * 31; break;
                        case 'ayear':   $expires = 86400 * 365; break;
                        default: $expires = null;
                    }
                    if ($expires !== null) {
                        $operator = ($_POST['dropZeroSelectType'] == 'within') ? '>' : '<';
                        $where .= sprintf(' AND %s %s %d',
                            $db->quoteIdentifier('time'),
                            $operator,
                            time() - $expires);
                    }
                }
                // ブラックリストに登録する
                $to_blacklist = !empty($_POST['dropZeroToBlackList']);
            } else {
                // あぼーん画像を削除し、ブラックリストに登録する
                $where = $db->quoteIdentifier('rank') . ' < 0';
                $to_blacklist = true;
            }

            $sql = sprintf('SELECT %s FROM %s WHERE %s;',
                $db->quoteIdentifier('id'),
                $db->quoteIdentifier($ini['General']['table']),
                $where);
            $result = $db->getAll($sql, null, DB_FETCHMODE_ORDERED | DB_FETCHMODE_FLIPPED);
            if (DB::isError($result)) {
                P2Util::pushInfoHtml($result->getMessage());
                break;
            }
            if ($result) {
                $target = $result[0];
                $removed_files = ImageCache2_DatabaseManager::remove($target, $to_blacklist);
            } else {
                $removed_files = array();
            }
            $flexy->setData('toBlackList', $to_blacklist);
            break;

        // PC用以外の作成済みサムネイルを消去する
        case 'clearThumb':
            $thumb_dir2 = $ini['General']['cachedir'] . '/' . $ini['Thumb2']['name'];
            $thumb_dir3 = $ini['General']['cachedir'] . '/' . $ini['Thumb3']['name'];
            $result_files2 = P2Util::garbageCollection($thumb_dir2, -1, '', '', true);
            $result_files3 = P2Util::garbageCollection($thumb_dir3, -1, '', '', true);
            $removed_files = array_merge($result_files2['successed'], $result_files3['successed']);
            $failed_files = array_merge($result_files2['failed'], $result_files3['failed']);
            if (!empty($failed_files)) {
                $info_msg_ht = '<p>以下のファイルが削除できませんでした。</p>';
                $info_msg_ht .= '<ul><li>';
                $info_msg_ht .= implode('</li><li>', array_map('p2h', $failed_files));
                $info_msg_ht .= '</li></ul>';
                P2Util::pushInfoHtml($info_msg_ht);
            }
            break;

        // 一覧表示用のデータキャッシュを消去する
        case 'clearCache':
            // 一覧表示用データキャッシュをクリア
            if ($viewer_cache_exists) {
                $kvs = P2KeyValueStore::getStore($_conf['iv2_cache_db_path'],
                                                 P2KeyValueStore::CODEC_SERIALIZING);
                if ($kvs->clear() === false) {
                    P2Util::pushInfoHtml('<p>一覧表示用のデータキャッシュを消去できませんでした。</p>');
                } else {
                    P2Util::pushInfoHtml('<p>一覧表示用のデータキャッシュを消去しました。</p>');
                }
            }

            // コンパイル済みテンプレートを削除
            $result_files = P2Util::garbageCollection($flexy->options['compileDir'], -1, '', '', true);
            $removed_files = $result_files['successed'];
            if (!empty($result_files['failed'])) {
                $info_msg_ht = '<p>以下のコンパイル済みテンプレートが削除できませんでした。</p>';
                $info_msg_ht .= '<ul><li>';
                $info_msg_ht .= implode('</li><li>', array_map('p2h', $result_files['failed']));
                $info_msg_ht .= '</li></ul>';
                P2Util::pushInfoHtml($info_msg_ht);
            }
            break;

        // エラーログを消去する
        case 'clearErrorLog':
            $result = $db->query('DELETE FROM ' . $db->quoteIdentifier($ini['General']['error_table']));
            if (DB::isError($result)) {
                P2Util::pushInfoHtml($result->getMessage());
            } else {
                P2Util::pushInfoHtml('<p>エラーログを消去しました。</p>');
            }
            break;

        // ブラックリストを消去する
        case 'clearBlackList':
            $result = $db->query('DELETE FROM ' . $db->quoteIdentifier($ini['General']['blacklist_table']));
            if (DB::isError($result)) {
                P2Util::pushInfoHtml($result->getMessage());
            } else {
                P2Util::pushInfoHtml('<p>ブラックリストを消去しました。</p>');
            }
            break;

        // データベースを最適化する
        case 'optimizeDB':
            // SQLite2 の画像キャッシュデータベースをVACUUM
            if ($db->dsn['phptype'] == 'sqlite') {
                $result = $db->query('VACUUM');
                if (DB::isError($result)) {
                    P2Util::pushInfoHtml($result->getMessage());
                } else {
                    P2Util::pushInfoHtml('<p>画像データベースを最適化しました。</p>');
                }
            }

            // SQLite3 の一覧表示用データキャッシュをVACUUM,REINDX
            if ($viewer_cache_exists) {
                $kvs = P2KeyValueStore::getStore($_conf['iv2_cache_db_path'],
                                                 P2KeyValueStore::CODEC_SERIALIZING);
                $kvs->optimize();
                unset($kvs);
                P2Util::pushInfoHtml('<p>一覧表示用のデータキャッシュを最適化しました。</p>');
            }
            break;

        // 未定義のリクエスト
        default:
            P2Util::pushInfoHtml('<p>未定義のリクエストです。</p>');
    }

    if (isset($removed_files)) {
        $flexy->setData('removedFiles', $removed_files);
    }
}

// }}}
// {{{ 出力

$flexy->setData('skin', $skin_en);
$flexy->setData('php_self', $_SERVER['SCRIPT_NAME']);
$flexy->setData('p2vid', P2_VERSION_ID);
$flexy->setData('info_msg', P2Util::getInfoHtml());
$flexy->setData('pc', !$_conf['ktai']);
$flexy->setData('iphone', $_conf['iphone']);
$flexy->setData('doctype', $_conf['doctype']);
$flexy->setData('extra_headers',   $_conf['extra_headers_ht']);
$flexy->setData('extra_headers_x', $_conf['extra_headers_xht']);
if ($db->dsn['phptype'] == 'sqlite' || $viewer_cache_exists) {
    $flexy->setData('enable_optimize_db', true);
} else {
    $flexy->setData('enable_optimize_db', false);
}

P2Util::header_nocache();
$flexy->compile('ic2mng.tpl.html');
$flexy->output();

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
