<?php
/**
 * ImageCache2 - 画像キャッシュ一覧
 */

// {{{ p2基本設定読み込み&認証

define('P2_SESSION_CLOSE_AFTER_AUTHENTICATION', 0);
define('P2_OUTPUT_XHTML', 1);

require_once __DIR__ . '/../init.php';

$_login->authorize();

if (!$_conf['expack.ic2.enabled']) {
    p2die('ImageCache2は無効です。', 'conf/conf_admin_ex.inc.php の設定を変えてください。');
}

$enable_zip = false;
if (!$_conf['ktai'] && $_conf['expack.ic2.zip']) {
    if (!extension_loaded('zip')) {
        p2die('zip 拡張モジュールがロードされていません。');
    }
    $enable_zip = true;
}

if ($_conf['iphone']) {
    $_conf['extra_headers_ht'] .= <<<EOP
\n<link rel="stylesheet" type="text/css" href="css/ic2_iphone.css?{$_conf['p2_version_id']}">
<link rel="stylesheet" type="text/css" href="css/iv2_iphone.css?{$_conf['p2_version_id']}">
<script type="text/javascript" src="js/json2.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/ic2_iphone.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/iv2_iphone.js?{$_conf['p2_version_id']}"></script>\n
EOP;
    $_conf['extra_headers_xht'] .= <<<EOP
\n<link rel="stylesheet" type="text/css" href="css/ic2_iphone.css?{$_conf['p2_version_id']}" />
<link rel="stylesheet" type="text/css" href="css/iv2_iphone.css?{$_conf['p2_version_id']}" />
<script type="text/javascript" src="js/json2.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/ic2_iphone.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/iv2_iphone.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}

// ビュー判定用の隠し要素

if ($_conf['view_forced_by_query']) {
    output_add_rewrite_var('b', $_conf['b']);
}

// }}}
// {{{ 初期化

// ライブラリ読み込み
require_once 'HTML/QuickForm.php';
require_once 'HTML/QuickForm/Renderer/ObjectFlexy.php';
require_once 'HTML/Template/Flexy.php';
require_once 'HTML/Template/Flexy/Element.php';
require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';
require_once P2EX_LIB_DIR . '/ImageCache2/QuickForm/Rules.php';

// }}}
// {{{ config

// 設定ファイル読み込み
$ini = ic2_loadconfig();

// Exif表示が有効か？
$show_exif = ($ini['Viewer']['exif'] && extension_loaded('exif'));

$_default_mode = (int)$_conf['expack.ic2.viewer_default_mode'];
if ($_default_mode < 0 || $_default_mode > 3) {
    $_default_mode = 0;
}

// フォームのデフォルト値
$_defaults = array(
    'page'  => 1,
    'cols'  => $ini['Viewer']['cols'],
    'rows'  => $ini['Viewer']['rows'],
    'inum'  => $ini['Viewer']['inum'],
    'order' => $ini['Viewer']['order'],
    'sort'  => $ini['Viewer']['sort'],
    'field' => $ini['Viewer']['field'],
    'keyword'   => '',
    'threshold' => $ini['Viewer']['threshold'],
    'compare' => $ini['Viewer']['compare'],
    'mode' => $_default_mode,
    'thumbtype' => ImageCache2_Thumbnailer::SIZE_DEFAULT,
);

// フォームの固定値
$_constants = array(
    'start'   => '<<',
    'prev'    => '<',
    'next'    => '>',
    'end'     => '>>',
    'jump'    => 'Go',
    'search'  => '検索',
    'cngmode' => '変更',
    '_hint'   => $_conf['detect_hint'],
);

// 閾値比較方法
$_compare = array(
    '>=' => '&gt;=',
    '='  => '=',
    '<=' => '&lt;=',
);

// 閾値
$_threshold = array(
    '-1' => '-1',
    '0' => '0',
    '1' => '1',
    '2' => '2',
    '3' => '3',
    '4' => '4',
    '5' => '5',
);

// ソート基準
$_order = array(
    'time' => '取得日時',
    'uri'  => 'URL',
    'date_uri' => '日付+URL',
    'date_uri2' => '日付+URL(2)',
    'name' => 'ファイル名',
    'size' => 'ファイルサイズ',
    'width' => '横幅',
    'height' => '高さ',
    'pixels' => 'ピクセル数',
    'id' => 'ID',
);

// ソート方向
$_sort = array(
    'ASC'  => '昇順',
    'DESC' => '降順',
);

// 検索フィールド
$_field = array(
    'uri'   => 'URL',
    'name'  => 'ファイル名',
    'memo'  => 'メモ',
    'md5'  => 'md5',
);

// モード
$_mode = array(
    '3' => 'ｻﾑﾈｲﾙだけ',
    '0' => '一覧',
    '1' => '一括変更',
    '2' => '個別管理',
);

// サムネイルタイプ
$_thumbtype = array(
    '1' => $ini['Thumb1']['name'],
    '2' => $ini['Thumb2']['name'],
    '3' => $ini['Thumb3']['name'],
);

// 携帯用に変換（フォームをパケット節約の対象外とするため）
if ($_conf['ktai']) {
    foreach ($_order as $_k => $_v) {
        $_order[$_k] = mb_convert_kana($_v, 'ask');
    }
    foreach ($_field as $_k => $_v) {
        $_field[$_k] = mb_convert_kana($_v, 'ask');
    }
}

// }}}
// {{{ prepare (DB & Cache)

// DB_DataObjectを継承したDAO
$icdb = new ImageCache2_DataObject_Images();
$db = $icdb->getDatabaseConnection();
$db_class = strtolower(get_class($db));

if ($ini['Viewer']['cache']) {
    $kvs = P2KeyValueStore::getStore($_conf['iv2_cache_db_path'],
                                     P2KeyValueStore::CODEC_SERIALIZING);
    $cache_lifetime = (int)$ini['Viewer']['cache_lifetime'];
    if (array_key_exists('cache_clean', $_REQUEST)) {
        $cache_clear = $_REQUEST['cache_clean'];
    } else {
        $cache_clear = false;
    }
    $optimize_db = false;

    if ($cache_clear == 'all') {
        $kvs->clear();
        $optimize_db = true;
    } elseif ($cache_clear == 'gc') {
        $kvs->gc($cache_lifetime);
        $optimize_db = true;
    }

    if ($optimize_db) {
        // キャッシュをVACUUM,REINDEX
        $kvs->optimize();

        // SQLiteならVACUUMを実行
        if ($db_class == 'db_sqlite') {
            $result = $db->query('VACUUM');
            if (DB::isError($result)) {
                p2die($result->getMessage());
            }
        }
    }

    $cache = new P2KeyValueStore_FunctionCache($kvs, $cache_lifetime);
    $imageInfo_getExtraInfo = $cache->createProxy('ImageCache2_ImageInfo::getExtraInfo');
    $imageInfo_getExifData = $cache->createProxy('ImageCache2_ImageInfo::getExifData');
    $editForm_imgManager = $cache->createProxy('ImageCache2_EditForm::imgManager');

    $use_cache = true;
} else {
    $use_cache = false;
}

// }}}
// {{{ prepare (Form & Template)

// ページ遷移用フォームを設定
// ページ遷移はGETで行うが、画像情報の更新はPOSTで行うのでどちらでも受け入れるようにする
// （レンダリング前に $qf->updateAttributes(array('method' => 'get')); とする）
$_attribures = array('accept-charset' => 'UTF-8,Shift_JIS');
$_method = ($_SERVER['REQUEST_METHOD'] == 'GET') ? 'get' : 'post';
$qf = new HTML_QuickForm('go', $_method, $_SERVER['SCRIPT_NAME'], '_self', $_attribures);
$qf->registerRule('numberInRange',  null, 'ImageCache2_QuickForm_Rule_NumberInRange');
$qf->registerRule('inArray',        null, 'ImageCache2_QuickForm_Rule_InArray');
$qf->registerRule('arrayKeyExists', null, 'ImageCache2_QuickForm_ArrayKeyExists');
$qf->setDefaults($_defaults);
$qf->setConstants($_constants);
$qfe = array();

// フォーム要素の定義

// ページ移動のためのsubmit要素
$qfe['start'] = $qf->addElement('button', 'start');
$qfe['prev']  = $qf->addElement('button', 'prev');
$qfe['next']  = $qf->addElement('button', 'next');
$qfe['end']   = $qf->addElement('button', 'end');
$qfe['jump']  = $qf->addElement('button', 'jump');

// 表示方法などを指定するinput要素
$qfe['page']      = $qf->addElement('text', 'page', 'ページ番号を指定', array('size' => 3));
$qfe['cols']      = $qf->addElement('text', 'cols', '横', array('size' => 3, 'maxsize' => 2));
$qfe['rows']      = $qf->addElement('text', 'rows', '縦', array('size' => 3, 'maxsize' => 2));
$qfe['order']     = $qf->addElement('select', 'order', '並び順', $_order);
$qfe['sort']      = $qf->addElement('select', 'sort', '方向', $_sort);
$qfe['field']     = $qf->addElement('select', 'field', 'フィールド', $_field);
$qfe['keyword']       = $qf->addElement('text', 'keyword', 'キーワード', array('size' => 20));
$qfe['compare']   = $qf->addElement('select', 'compare', '比較方法', $_compare);
$qfe['threshold'] = $qf->addElement('select', 'threshold', 'しきい値', $_threshold);
$qfe['thumbtype'] = $qf->addElement('select', 'thumbtype', 'サムネイルタイプ', $_thumbtype);

// 文字コード判定のヒントにする隠し要素
$qfe['_hint'] = $qf->addElement('hidden', '_hint');

// 検索を実行するsubmit要素
$qfe['search'] = $qf->addElement('submit', 'search');

// モード変更をするselect要素
$qfe['mode'] = $qf->addElement('select', 'mode', 'モード', $_mode);

// モード変更を確定するsubmit要素
$qfe['cngmode'] = $qf->addElement('submit', 'cngmode');

// フォームのルール
$qf->addRule('cols', '1 to 20',  'numberInRange', array('min' => 1, 'max' => 20),  'client', true);
$qf->addRule('rows', '1 to 100', 'numberInRange', array('min' => 1, 'max' => 100), 'client', true);
$qf->addRule('order', 'invalid order.', 'arrayKeyExists', $_order);
$qf->addRule('sort',  'invalid sort.',  'arrayKeyExists', $_sort);
$qf->addRule('field', 'invalid field.', 'arrayKeyExists', $_field);
$qf->addRule('threshold', '-1 to 5', 'numberInRange', array('min' => -1, 'max' => 5));
$qf->addRule('compare', 'invalid compare.', 'arrayKeyExists', $_compare);
$qf->addRule('mode', 'invalid mode.', 'arrayKeyExists', $_mode);
$qf->addRule('thumbtype', 'invalid thumbtype.', 'arrayKeyExists', $_thumbtype);

// Flexy
$_flexy_options = array(
    'locale' => 'ja',
    'charset' => 'Shift_JIS',
    'compileDir' => $_conf['compile_dir'] . DIRECTORY_SEPARATOR . 'iv2',
    'templateDir' => P2EX_LIB_DIR . '/ImageCache2/templates',
    'numberFormat' => '', // ",0,'.',','" と等価
    'plugins' => array('P2Util' => P2_LIB_DIR . '/P2Util.php')
);

if (!is_dir($_conf['compile_dir'])) {
    FileCtl::mkdirRecursive($_conf['compile_dir']);
}

$flexy = new HTML_Template_Flexy($_flexy_options);

$flexy->setData('php_self', $_SERVER['SCRIPT_NAME']);
$flexy->setData('base_dir', dirname($_SERVER['SCRIPT_NAME']));
$flexy->setData('p2vid', P2_VERSION_ID);
$flexy->setData('_hint', $_conf['detect_hint']);
if ($_conf['iphone']) {
    $flexy->setData('top_url', 'index.php');
} elseif ($_conf['ktai']) {
    $flexy->setData('k_color', array(
        'c_bgcolor' => !empty($_conf['mobile.background_color']) ? $_conf['mobile.background_color'] : '#ffffff',
        'c_text'    => !empty($_conf['mobile.text_color'])  ? $_conf['mobile.text_color']  : '#000000',
        'c_link'    => !empty($_conf['mobile.link_color'])  ? $_conf['mobile.link_color']  : '#0000ff',
        'c_vlink'   => !empty($_conf['mobile.vlink_color']) ? $_conf['mobile.vlink_color'] : '#9900ff',
    ));
    $flexy->setData('top_url', dirname($_SERVER['SCRIPT_NAME']) . '/index.php');
    $flexy->setData('accesskey', $_conf['accesskey']);
} else {
    $flexy->setData('skin', str_replace('&amp;', '&', $skin_en));
}
$flexy->setData('pc', !$_conf['ktai']);
$flexy->setData('iphone', $_conf['iphone']);
$flexy->setData('doctype', $_conf['doctype']);
$flexy->setData('extra_headers',   $_conf['extra_headers_ht']);
$flexy->setData('extra_headers_x', $_conf['extra_headers_xht']);

// }}}
// {{{ validate

// 検証
$qf->validate();
$sv = $qf->getSubmitValues();
$page      = ImageCache2_ParameterUtility::getValidValue('page',   $_defaults['page'], 'intval');
$cols      = ImageCache2_ParameterUtility::getValidValue('cols',   $_defaults['cols'], 'intval');
$rows      = ImageCache2_ParameterUtility::getValidValue('rows',   $_defaults['rows'], 'intval');
$order     = ImageCache2_ParameterUtility::getValidValue('order',  $_defaults['order']);
$sort      = ImageCache2_ParameterUtility::getValidValue('sort',   $_defaults['sort'] );
$field     = ImageCache2_ParameterUtility::getValidValue('field',  $_defaults['field']);
$key       = ImageCache2_ParameterUtility::getValidValue('keyword',    $_defaults['keyword']);
$threshold = ImageCache2_ParameterUtility::getValidValue('threshold', $_defaults['threshold'], 'intval');
$compare   = ImageCache2_ParameterUtility::getValidValue('compare',   $_defaults['compare']);
$mode      = ImageCache2_ParameterUtility::getValidValue('mode',      $_defaults['mode'], 'intval');
$thumbtype = ImageCache2_ParameterUtility::getValidValue('thumbtype', $_defaults['thumbtype'], 'intval');

// サムネイル作成クラス
$thumbsize = $thumbtype;
if (!empty($_SESSION['device_pixel_ratio'])) {
    $dpr = $_SESSION['device_pixel_ratio'];
    if ($dpr === 1.5) {
        $thumbsize |= ImageCache2_Thumbnailer::DPR_1_5;
    } elseif ($dpr === 2.0) {
        $thumbsize |= ImageCache2_Thumbnailer::DPR_2_0;
    } else {
        $dpr = 1.0;
    }
} else {
    $dpr = 1.0;
}
$thumb = new ImageCache2_Thumbnailer($thumbsize);

// 携帯用に調整
if ($_conf['ktai']) {
    $lightbox = false;
    $mode = 1;
    $inum = (int) $ini['Viewer']['inum'];
    $overwritable_params = array('order', 'sort', 'field', 'keyword', 'threshold', 'compare');

    // 絵文字を読み込む
    require_once P2_LIB_DIR . '/emoji.inc.php';
    $emj = p2_get_emoji();
    $flexy->setData('e', $emj);
    $flexy->setData('ak', $_conf['k_accesskey_at']);
    $flexy->setData('as', $_conf['k_accesskey_st']);

    // フィルタリング用フォームを表示
    if (!empty($_GET['show_iv2_kfilter'])) {
        !defined('P2_NO_SAVE_PACKET') && define('P2_NO_SAVE_PACKET', true);
        $r = new HTML_QuickForm_Renderer_ObjectFlexy($flexy);
        $qfe['keyword']->removeAttribute('size');
        $qf->updateAttributes(array('method' => 'get'));
        $qf->accept($r);
        $qfObj = $r->toObject();
        $flexy->setData('page', $page);
        $flexy->setData('move', $qfObj);
        P2Util::header_nocache();
        $flexy->compile('iv2if.tpl.html');
        $flexy->output();
        exit;
    }
    // フィルタをリセット
    elseif (!empty($_GET['reset_filter'])) {
        unset($_SESSION['iv2i_filter']);
        session_write_close();
    }
    // フィルタを設定
    elseif (!empty($_GET['session_no_close'])) {
        foreach ($overwritable_params as $ow_key) {
            if (isset($$ow_key)) {
                $_SESSION['iv2i_filter'][$ow_key] = $$ow_key;
            }
        }
        session_write_close();
    }
    // フィルタリング用変数を更新
    elseif (!empty($_SESSION['iv2i_filter'])) {
        foreach ($overwritable_params as $ow_key) {
            if (isset($_SESSION['iv2i_filter'][$ow_key])) {
                $$ow_key = $_SESSION['iv2i_filter'][$ow_key];
            }
        }
    }
} else {
    //$lightbox = ($mode == 0 || $mode == 3) ? $ini['Viewer']['lightbox'] : false;
    $lightbox = $ini['Viewer']['lightbox'];
}

// }}}
// {{{ query

$removed_files = array();

// 閾値でフィルタリング
if (!($threshold == -1 && $compare == '>=')) {
    $icdb->whereAddQuoted('rank', $compare, $threshold);
}

// キーワード検索をするとき
if ($key !== '') {
    $keys = explode(' ', $icdb->uniform($key, 'CP932', $field == 'memo'));
    foreach ($keys as $k) {
        $operator = 'LIKE';
        $wildcard = '%';
        $not = false;
        if ($k[0] == '-' && strlen($k) > 1) {
            $not = true;
            $k = substr($k, 1);
        }
        if (strpos($k, '%') !== false || strpos($k, '_') !== false) {
            // SQLite2はLIKE演算子の右辺でバックスラッシュによるエスケープや
            // ESCAPEでエスケープ文字を指定することができないのでGLOB演算子を使う
            if ($db_class == 'db_sqlite') {
                if (strpos($k, '*') !== false || strpos($k, '?') !== false) {
                    p2die('ImageCache2 Warning', '「%または_」と「*または?」が混在するキーワードは使えません。');
                } else {
                    $operator = 'GLOB';
                    $wildcard = '*';
                }
            } else {
                $k = preg_replace('/[%_]/', '\\\\$0', $k);
            }
        }
        $expr = $wildcard . $k . $wildcard;
        if ($not) {
            $operator = 'NOT ' . $operator;
        }
        $icdb->whereAddQuoted($field, $operator, $expr);
    }
    $qfe['keyword']->setValue($key);
}

// 重複画像をスキップするとき
if ($ini['Viewer']['unique'] == 1) {
    $_find_unique = true;
} elseif ($ini['Viewer']['unique'] == 2 &&
          in_array($order, array('size', 'width', 'height', 'pixels'))) {
    $_find_unique = true;
} else {
    $_find_unique = false;
}
$_find_duplicated = 0; // 試験的パラメータ、登録レコード数がこれ以上の画像のみを抽出
if ($_find_unique || $_find_duplicated > 1) {
    $subq = 'SELECT ' . (($sort == 'ASC') ? 'MIN' : 'MAX') . '(id) FROM ';
    $subq .= $db->quoteIdentifier($ini['General']['table']);
    if (isset($keys)) {
        // サブクエリ内でフィルタリングするので親クエリのWHERE句をパクってきてリセット
        $subq .= $icdb->_query['condition'];
        $icdb->whereAdd();
    }
    // md5だけでグループ化しても十分とは思うけど、一応。
    $subq .= ' GROUP BY size, md5, mime';
    if ($_find_duplicated > 1) {
        $subq .= sprintf(' HAVING COUNT(*) > %d', $_find_duplicated - 1);
    }
    // echo '<!--', mb_convert_encoding($subq, 'CP932', 'UTF-8'), '-->';
    $icdb->whereAdd("id IN ($subq)");
}

// データベースを更新するとき
if (isset($_POST['edit_submit']) && !empty($_POST['change'])) {

    $target = array_unique(array_map('intval', $_POST['change']));

    switch ($mode) {

    // 一括でパラメータ変更
    case 1:
        // ランクを変更
        $newrank = ImageCache2_ParameterUtility::intoRange($_POST['setrank'], -1, 5);
        ImageCache2_DatabaseManager::setRank($target, $newrank);
        // メモを追加
        if (!empty($_POST['addmemo'])) {
            $newmemo = get_magic_quotes_gpc() ? stripslashes($_POST['addmemo']) : $_POST['addmemo'];
            $newmemo = $icdb->uniform($newmemo, 'CP932');
            if ($newmemo !== '') {
                 ImageCache2_DatabaseManager::addMemo($target, $newmemo);
            }
        }
        break;

    // 個別にパラメータ変更
    case 2:
        // 更新用のデータをまとめる
        $updated = array();
        $removed = array();
        $to_blacklist = false;
        $no_blacklist = false;

        foreach ($target as $id) {
            if (!empty($_POST['img'][$id]['remove'])) {
                if (!empty($_POST['img'][$id]['black'])) {
                    $to_blacklist = true;
                    $removed[$id] = true;
                } else {
                    $no_blacklist = true;
                    $removed[$id] = false;
                }
            } else {
                $newmemo = get_magic_quotes_gpc() ? stripslashes($_POST['img'][$id]['memo']) : $_POST['img'][$id]['memo'];
                $data = array(
                    'rank' => intval($_POST['img'][$id]['rank']),
                    'memo' => $icdb->uniform($newmemo, 'CP932')
                );
                if (0 < $id && -1 <= $data['rank'] && $data['rank'] <= 5) {
                    $updated[$id] = $data;
                }
            }
        }

        // 情報を更新
        if (count($updated) > 0) {
            ImageCache2_DatabaseManager::update($updated);
        }

        // 削除（＆ブラックリスト送り）
        if (count($removed) > 0) {
            foreach ($removed as $id => $to_blacklist) {
                $removed_files = array_merge($removed_files,
                                             ImageCache2_DatabaseManager::remove(array($id), $to_blacklist));
            }
            if ($to_blacklist) {
                if ($no_blacklist) {
                    $flexy->setData('toBlackListAll', false);
                    $flexy->setData('toBlackListPartial', true);
                } else {
                    $flexy->setData('toBlackListAll', true);
                    $flexy->setData('toBlackListPartial', false);
                }
            } else {
                $flexy->setData('toBlackListAll', false);
                $flexy->setData('toBlackListPartial', false);
            }
        }
        break;

    } // endswitch

// 一括で画像を削除するとき
} elseif ($mode == 1 && isset($_POST['edit_remove']) && !empty($_POST['change'])) {
    $target = array_unique(array_map('intval', $_POST['change']));
    $to_blacklist = !empty($_POST['edit_toblack']);
    $removed_files = array_merge($removed_files,
                                 ImageCache2_DatabaseManager::remove($target, $to_blacklist));
    $flexy->setData('toBlackList', $to_blacklist);
}

// }}}
// {{{ build

// 総レコード数を数える
//$db->setFetchMode(DB_FETCHMODE_ORDERED);
//$all = (int)$icdb->count('*', true);
//$db->setFetchMode(DB_FETCHMODE_ASSOC);
$sql = sprintf('SELECT COUNT(*) FROM %s %s', $db->quoteIdentifier($ini['General']['table']), $icdb->_query['condition']);
$all = (int)$db->getOne($sql);
if (DB::isError($all)) {
    p2die($all->getMessage());
}

// マッチするレコードがなかったらエラーを表示、レコードがあれば表示用オブジェクトに値を代入
if ($all === 0) {

    // レコードなし
    $flexy->setData('nomatch', true);
    $flexy->setData('reset', $_SERVER['SCRIPT_NAME']);
    if ($_conf['ktai']) {
        $flexy->setData('kfilter', !empty($_SESSION['iv2i_filter']));
    }
    $qfe['start']->updateAttributes('disabled');
    $qfe['prev']->updateAttributes('disabled');
    $qfe['next']->updateAttributes('disabled');
    $qfe['end']->updateAttributes('disabled');
    $qfe['page']->updateAttributes('disabled');
    $qfe['jump']->updateAttributes('disabled');

} else {

    // レコードあり
    $flexy->setData('nomatch', false);

    // 表示範囲を設定
    $ipp = $_conf['ktai'] ? $inum : $cols * $rows; // images per page
    $last_page = ceil($all / $ipp);

    // ページ遷移用パラメータを準備
    if (isset($sv['search']) || isset($sv['cngmode'])) {
        $page = 1;
    } elseif (isset($sv['page'])) {
        $page = max(1, min((int)$sv['page'], $last_page));
    } else {
        $page = 1;
    }
    $prev_page = max(1, $page - 1);
    $next_page = min($page + 1, $last_page);

    // ページ遷移用リンク（iPhone）を生成
    if ($_conf['iphone']) {
        $pg_base = p2h($_SERVER['SCRIPT_NAME']);
        $pager = '';
        if ($page != 1) {
            $pager .= sprintf('<a href="%s?page=%d">%s</a> ', $pg_base,          1, $emj['lt2']);
            $pager .= sprintf('<a href="%s?page=%d">%s</a> ', $pg_base, $prev_page, $emj['lt1']);
        }
        $pager .= sprintf('%d/%d', $page, $last_page);
        if ($page != $last_page) {
            $pager .= sprintf(' <a href="%s?page=%d">%s</a>', $pg_base, $next_page, $emj['rt1']);
            $pager .= sprintf(' <a href="%s?page=%d">%s</a>', $pg_base, $last_page, $emj['rt2']);
        }
        $flexy->setData('pager', $pager);

    // ページ遷移用リンク（携帯）を生成
    } elseif ($_conf['ktai']) {
        $pg_base = p2h($_SERVER['SCRIPT_NAME']);
        $pg_pos = sprintf('%d/%d', $page, $last_page);
        $pager1 = '';
        $pager2 = '';
        if ($page != 1) {
            $pager1 .= sprintf('<a href="%s?page=%d"%s>%s%s</a> ',
                               $pg_base,
                               1,
                               $_conf['k_accesskey_at'][1],
                               $_conf['k_accesskey_st'][1],
                               $emj['lt2']
                               );
            $pager1 .= sprintf('<a href="%s?page=%d"%s>%s%s</a> ',
                               $pg_base,
                               $prev_page,
                               $_conf['k_accesskey_at'][4],
                               $_conf['k_accesskey_st'][4],
                               $emj['lt1']
                               );
            $pager2 .= sprintf('<a href="%s?page=%d">%s</a> ', $pg_base,          1, $emj['lt2']);
            $pager2 .= sprintf('<a href="%s?page=%d">%s</a> ', $pg_base, $prev_page, $emj['lt1']);
        }
        $pager1 .= $pg_pos;
        $pager2 .= $pg_pos;
        if ($page != $last_page) {
            $pager1 .= sprintf(' <a href="%s?page=%d">%s</a>', $pg_base, $next_page, $emj['rt1']);
            $pager1 .= sprintf(' <a href="%s?page=%d">%s</a>', $pg_base, $last_page, $emj['rt2']);
            $pager2 .= sprintf(' <a href="%s?page=%d"%s>%s%s</a>',
                               $pg_base,
                               $next_page,
                               $_conf['k_accesskey_at'][6],
                               $_conf['k_accesskey_st'][6],
                               $emj['rt1']
                               );
            $pager2 .= sprintf(' <a href="%s?page=%d"%s>%s%s</a>',
                               $pg_base,
                               $last_page,
                               $_conf['k_accesskey_at'][9],
                               $_conf['k_accesskey_st'][9],
                               $emj['rt2']
                               );
        }
        $flexy->setData('pager1', $pager1);
        $flexy->setData('pager2', $pager2);

    // ページ遷移用フォーム（PC）を生成
    } else {
        $mf_hiddens = array(
            '_hint' => $_conf['detect_hint'], 'mode' => $mode,
            'page' => $page, 'cols' => $cols, 'rows' => $rows,
            'order' => $order, 'sort' => $sort,
            'field' => $field, 'keyword' => $key,
            'compare' => $compare, 'threshold' => $threshold,
            'thumbtype' => $thumbtype
        );
        $pager_q = $mf_hiddens;
        mb_convert_variables('UTF-8', 'CP932', $pager_q);

        // ページ番号を更新
        $qfe['page']->setValue($page);
        $qf->addRule('page', "1 to {$last_page}", 'numberInRange', array('min' => 1, 'max' => $last_page), 'client', true);

        // 一時的にパラメータ区切り文字を & にして現在のページのURLを生成
        $pager_separator = ini_get('arg_separator.output');
        ini_set('arg_separator.output', '&');
        $flexy->setData('current_page', $_SERVER['SCRIPT_NAME'] . '?' . http_build_query($pager_q));
        ini_set('arg_separator.output', $pager_separator);
        unset($pager_q, $pager_separator);

        // ページ後方移動ボタンの属性を更新
        if ($page == 1) {
            $qfe['start']->updateAttributes('disabled');
            $qfe['prev']->updateAttributes('disabled');
        } else {
            $qfe['start']->updateAttributes(array('onclick' => "pageJump(1)"));
            $qfe['prev']->updateAttributes(array('onclick' => "pageJump({$prev_page})"));
        }

        // ページ前方移動ボタンの属性を更新
        if ($page == $last_page) {
            $qfe['next']->updateAttributes('disabled');
            $qfe['end']->updateAttributes('disabled');
        } else {
            $qfe['next']->updateAttributes(array('onclick' => "pageJump({$next_page})"));
            $qfe['end']->updateAttributes(array('onclick' => "pageJump({$last_page})"));
        }

        // ページ指定移動用ボタンの属性を更新
        if ($last_page == 1) {
            $qfe['jump']->updateAttributes('disabled');
        } else {
            $qfe['jump']->updateAttributes(array('onclick' => "if(validate_go(this.form))pageJump(this.form.page.value)"));
        }
    }

    // 編集モード用フォームを生成
    if ($mode == 1 || $mode == 2) {
        $flexy->setData('editFormHeader', ImageCache2_EditForm::header((isset($mf_hiddens) ? $mf_hiddens : array()), $mode));
        if ($mode == 1) {
            $flexy->setData('editFormCheckAllOn', ImageCache2_EditForm::checkAllOn());
            $flexy->setData('editFormCheckAllOff', ImageCache2_EditForm::checkAllOff());
            $flexy->setData('editFormCheckAllReverse', ImageCache2_EditForm::checkAllReverse());
            $flexy->setData('editFormSelect', ImageCache2_EditForm::selectRank($_threshold));
            $flexy->setData('editFormText', ImageCache2_EditForm::textMemo());
            $flexy->setData('editFormSubmit', ImageCache2_EditForm::submit());
            $flexy->setData('editFormReset', ImageCache2_EditForm::reset());
            $flexy->setData('editFormRemove', ImageCache2_EditForm::remove());
            $flexy->setData('editFormBlackList', ImageCache2_EditForm::toblack());
        } elseif ($mode == 2) {
            $flexy->setData('editForm', new ImageCache2_EditForm_Object);
        }
    }

    // DBから取得する範囲を設定して検索
    $from = ($page - 1) * $ipp;
    if ($order == 'pixels') {
        if (preg_match('/^(my|pg)sql:/', $ini['General']['dsn'])) {
            $orderBy = '(CAST(width AS INTEGER) * CAST(height AS INTEGER)) ' . $sort;
        } else {
            $orderBy = '(width * height) ' . $sort;
        }
    } elseif ($order == 'date_uri' || $order == 'date_uri2') {
        if ($db_class == 'db_sqlite') {
            /*
            function iv2_sqlite_unix2date($ts)
            {
                return date('Ymd', (int)$ts);
            }
            sqlite_create_function($db->connection, 'unix2date', 'iv2_sqlite_unix2date', 1);
            $time2date = 'unix2date("time")';
            */
            $time2date = 'php(\'date\', \'Ymd\', "time")';
        } else {
            // 32400 = 9*60*60 (時差補正)
            $time2date = sprintf('floor((%s + 32400) / 86400)', $db->quoteIdentifier('time'));
        }
        $orderBy = sprintf('%s %s, %s ', $time2date, $sort, $db->quoteIdentifier('uri'));
        if ($order == 'date_uri') {
            $orderBy .= $sort;
        } else {
            $orderBy .= ($sort == 'ASC') ? 'DESC' : 'ASC';
        }
    } else {
        $orderBy = $db->quoteIdentifier($order) . ' ' . $sort;
    }
    $orderBy .= ' , id ' . $sort;
    $icdb->orderBy($orderBy);
    $icdb->limit($from, $ipp);
    $found = $icdb->find();

    // テーブルのブロックに表示する値を取得&オブジェクトに代入
    $flexy->setData('all',  $all);
    $flexy->setData('cols', $cols);
    $flexy->setData('last', $last_page);
    $flexy->setData('from', $from + 1);
    $flexy->setData('to',   $from + $found);
    $flexy->setData('submit', array());
    $flexy->setData('reset', array());

    if ($_conf['ktai']) {
        $show_exif = false;
        $popup = false;
        $r_type = ($ini['General']['redirect'] == 1) ? 1 : 2;
    } else {
        switch ($mode) {
            case 3:
                $show_exif = false;
            case 2:
                $popup = false;
                break;
            default:
                $popup = true;
        }
        $r_type = 1;
    }
    $items = array();
    if (!empty($_SERVER['REQUEST_URI'])) {
        $k_backto = '&from=' . rawurlencode($_SERVER['REQUEST_URI']);
    } else {
        $k_backto = '';
    }

    while ($icdb->fetch()) {
        // 検索結果を配列にし、レンダリング用の要素を付加
        // 配列どうしなら+演算子で要素を追加できる
        // （キーの重複する値を上書きしたいときはarray_merge()を使う）
        $img = $icdb->toArray();
        mb_convert_variables('CP932', 'UTF-8', $img);
        // ランク・メモは変更されることが多く、一覧用のデータキャッシュに影響を与えないように別に処理する
        $status = array();
        $status['rank'] = $img['rank'];
        $status['rank_f'] = ($img['rank'] == -1) ? 'あぼーん' : $img['rank'];
        if ($img['rank'] == -1) {
            $status['rank_i'] = '<img src="img/sn1a.png" width="16" height="16">';
        } elseif ($img['rank'] > 0 && $img['rank'] <= 5) {
            $status['rank_i'] = str_repeat('<img src="img/s1a.png" width="16" height="16">', $img['rank']);
        } else {
            $status['rank_i'] = '';
        }
        $status['memo'] = $img['memo'];
        unset($img['rank'], $img['memo']);

        // 表示用変数を設定
        if ($use_cache) {
            $add = $imageInfo_getExtraInfo->invoke($img);
            if ($mode == 1) {
                $chk = ImageCache2_EditForm::imgChecker($img); // 比較的軽いのでキャッシュしない
                $add += $chk;
            } elseif ($mode == 2) {
                $mng = $editForm_imgManager->invoke($img, $status);
                $add += $mng;
            }
        } else {
            $add = ImageCache2_ImageInfo::getExtraInfo($img);
            if ($mode == 1) {
                $chk = ImageCache2_EditForm::imgChecker($img);
                $add += $chk;
            } elseif ($mode == 2) {
                $mng = ImageCache2_EditForm::imgManager($img, $status);
                $add += $mng;
            }
        }
        // オリジナル画像が存在しないレコードを自動で削除
        if ($ini['Viewer']['delete_src_not_exists'] && !file_exists($add['src'])) {
            $add['thumb_k'] = $add['thumb'] = 'img/ic_removed.png';
            $add['t_width'] = $add['t_height'] = 32;
            $to_blacklist = false;
            $removed_files = array_merge($removed_files,
                                         ImageCache2_DatabaseManager::remove(array($img['id'], $to_blacklist)));
            $flexy->setData('toBlackList', $to_blacklist);
        } else {
            // サムネイルのパスのみdevicePixelRatioが影響するので再取得
            $add['thumb'] = $thumb->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);
            if (!file_exists($add['thumb'])) {
                // レンダリング時に自動でhtmlspecialchars()されるので&amp;にしない
                $add['thumb'] = 'ic2.php?r=' . $r_type . "&t={$thumb->mode}";
                if (file_exists($add['src'])) {
                    $add['thumb'] .= '&id=' . $img['id'];
                } else {
                    $add['thumb'] .= '&uri=' . rawurlencode($img['uri']);
                }
                if ($dpr === 1.5 || $dpr === 2.0) {
                    $add['thumb'] .= '&d=' . $dpr;
                }
            }
            if ($_conf['ktai']) {
                $add['thumb_k'] = 'ic2.php?r=0&t=2';
                if (file_exists($add['src'])) {
                    $add['thumb_k'] .= '&id=' . $img['id'];
                } else {
                    $add['thumb_k'] .= '&uri=' . rawurlencode($img['uri']);
                }
                $add['thumb_k'] .= $k_backto;
                if ($dpr === 1.5 || $dpr === 2.0) {
                    $add['thumb_k'] .= '&d=' . $dpr;
                }
            }
        }
        $item = array_merge($img, $add, $status);

        // Exif情報を取得
        if ($show_exif && file_exists($add['src']) && $img['mime'] == 'image/jpeg') {
            if ($use_cache) {
                $item['exif'] = $imageInfo_getExifData->invoke($add['src']);
            } else {
                $item['exif'] = ImageCache2_ImageInfo::getExifData($add['src']);
            }
        } else {
            $item['exif'] = null;
        }

        // Lightbox Plus 用パラメータを設定
        if ($lightbox) {
            $item['lightbox_attrs'] = ' rel="lightbox[iv2]" class="ineffectable"';
            $item['lightbox_attrs'] .= ' title="' . p2h($item['memo']) . '"';
        } else {
            $item['lightbox_attrs'] = '';
        }

        $items[] = $item;
    }

    $i = count($items); // == $found
    // テーブルの余白を埋めるためにnullを挿入
    if (!$_conf['ktai'] && $i > $cols && ($j = $i % $cols) > 0) {
        for ($k = 0; $k < $cols - $j; $k++) {
            $items[] = null;
            $i++;
        }
    }
    // この時点で $i == $cols * 自然数

    $flexy->setData('items', $items);
    $flexy->setData('popup', $popup);
    $flexy->setData('matrix', new ImageCache2_Matrix($cols, $rows, $i));
}

$flexy->setData('removedFiles', $removed_files);

// }}}
// {{{ output

// モード別の最終処理
if ($_conf['ktai']) {
    $title = str_replace('ImageCache2', 'IC2', $ini['Viewer']['title']);
    $list_template = ($_conf['iphone']) ? 'iv2ip.tpl.html' : 'iv2i.tpl.html';
} else {
    switch ($mode) {
        case 2:
            $title = $ini['Manager']['title'];
            $list_template = 'iv2m.tpl.html';
            break;
        case 1:
            $title = $ini['Viewer']['title'];
            $list_template = 'iv2a.tpl.html';
            break;
        default:
            $title = $ini['Viewer']['title'];
            $list_template = 'iv2.tpl.html';
    }
}

// フォームを最終調整し、テンプレート用オブジェクトに変換
$r = new HTML_QuickForm_Renderer_ObjectFlexy($flexy);
//$r->setLabelTemplate('_label.tpl.html');
//$r->setHtmlTemplate('_html.tpl.html');
$qf->updateAttributes(array('method' => 'get')); // リクエストをPOSTでも受け入れるため、ここで変更
/*if ($_conf['input_type_search']) {
    $input_type_search_attributes = array(
        'type' => 'search',
        'autosave' => 'rep2.expack.search.imgcache',
        'results' => '10',
        'placeholder' => '',
    );
    $qfe['key']->updateAttributes($input_type_search_attributes);
}*/
$qf->accept($r);
$qfObj = $r->toObject();

// 変数をAssign
$js = $qf->getValidationScript() . <<<EOJS
\n<script type="text/javascript">
// <![CDATA[
var ic2_cols = {$cols};
var ic2_rows = {$rows};
var ic2_lightbox_options = {
    no_loop: false,
    no_updown: false
};
// ]]>
</script>\n
EOJS;
$flexy->setData('title', $title);
$flexy->setData('mode', $mode);
$flexy->setData('js', $js);
$flexy->setData('page', $page);
$flexy->setData('move', $qfObj);
$flexy->setData('lightbox', $lightbox);
if ($enable_zip) {
    $flexy->setData('jquery', $_conf['jquery_version']);
} else {
    $flexy->setData('jquery', null);
}

// ページを表示
P2Util::header_nocache();
$flexy->compile($list_template);
if ($list_template == 'iv2ip.tpl.html') {
    if (!isset($prev_page)) {
        $prev_page = $page;
    }
    if (!isset($next_page)) {
        $next_page = $page;
    }
    $ll_autoactivate = empty($_GET['ll_autoactivate']) ? 'false' : 'true';
    $limelight_header = <<<EOP
<link rel="stylesheet" type="text/css" href="css/limelight.css?{$_conf['p2_version_id']}">
<script type="text/javascript" src="js/jquery-{$_conf['jquery_version']}.min.js"></script>
<script type="text/javascript" src="js/limelight.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript">
// <![CDATA[
document.addEventListener('DOMContentLoaded', function(event) {
    var limelight, slide;

    document.removeEventListener(event.type, arguments.callee, false);

    limelight = new Limelight({ 'savable': true });
    slide = limelight.bind();

    if ({$page} != {$prev_page}) {
        slide.onNoPrev = function(limelight, slide) {
            limelight.deactivate();
            window.location.href = 'iv2.php?page={$prev_page}&ll_autoactivate=1#bottom';
       };
    }

    if ({$page} != {$next_page}) {
        slide.onNoNext = function(limelight, slide) {
            limelight.deactivate();
            window.location.href = 'iv2.php?page={$next_page}&ll_autoactivate=1#top';
       };
    }

    if ({$ll_autoactivate}) {
        window.setTimeout(function(cursor) {
            limelight.activateSlide(slide, cursor);
        }, 100, (window.location.hash == '#bottom') ? -1 : 0);
    }
}, false);
// ]]>
</script>\n
EOP;
    $thumb_width = (int)$ini['Thumb1']['width'];
    $thumb_height = (int)$ini['Thumb1']['height'];
    $flexy->setData('thumb_width', $thumb_width);
    $flexy->setData('thumb_height', $thumb_height);
    $flexy->setData('info_vertical', $thumb_width > 80);
    $flexy->setData('limelight_header', $limelight_header);
    $flexy->output();
} elseif ($list_template == 'iv2i.tpl.html') {
    $mobile = Net_UserAgent_Mobile::singleton();
    $elements = $flexy->getElements();
    if ($mobile->isDoCoMo()) {
        $elements['page']->setAttributes('istyle="4"');
    } elseif ($mobile->isEZweb()) {
        $elements['page']->setAttributes('format="*N"');
    } elseif ($mobile->isSoftBank()) {
        $elements['page']->setAttributes('mode="numeric"');
    }
    $view = null;
    $flexy->outputObject($view, $elements);
} else {
    $flexy->output();
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
