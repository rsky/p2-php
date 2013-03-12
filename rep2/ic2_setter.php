<?php
/**
 * ImageCache2 - アップローダ
 */

// {{{ p2基本設定読み込み&認証

define('P2_OUTPUT_XHTML', 1);

require_once __DIR__ . '/../init.php';

$_login->authorize();

if (!$_conf['expack.ic2.enabled']) {
    p2die('ImageCache2は無効です。', 'conf/conf_admin_ex.inc.php の設定を変えてください。');
}

// }}}
// {{{ ライブラリ読み込み

require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';

// }}}
// {{{ config

// 設定ファイル読み込み
$ini = ic2_loadconfig();

// 最大ファイルサイズを設定
$ic2_maxsize = $ini['Source']['maxsize'];
if (preg_match('/(\d+\.?\d*)([KMG])/i', $ic2_maxsize, $m)) {
    $ic2_maxsize = p2_si2int($m[1], $m[2]);
} else {
    $ic2_maxsize = (int)$ic2_maxsize;
}

$ini_maxsize = ini_get('upload_max_filesize');
if (preg_match('/(\d+\.?\d*)([KMG])/i', $ini_maxsize, $m)) {
    $ini_maxsize = p2_si2int($m[1], $m[2]);
} else {
    $ini_maxsize = (int)$ini_maxsize;
}

if (0 < $ic2_maxsize && $ic2_maxsize < $ini_maxsize) {
    $maxsize    = $ic2_maxsize;
    $maxsize_si = $ini['Source']['maxsize'];
} else {
    $maxsize    = $ini_maxsize;
    $maxsize_si = ini_get('upload_max_filesize');
}

$maxwidth  = (int)$ini['Source']['maxwidth'] ;
$maxheight = (int)$ini['Source']['maxheight'];

// ポップアップウインドウ？
$isPopUp = empty($_REQUEST['popup']) ? 0 : 1;

// 対応MIMEタイプ
$mimemap = array(IMAGETYPE_GIF => 'image/gif', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png');
$mimeregex = '{^(image/(p?jpeg|png|gif)|application/octet-stream)$}';

// エラーメッセージのフォーマット
$err_fmt = array();
$err_fmt['none'] = "<p>Error: ファイルが一つも選ばれていません。</p>\n";
$err_fmt['file'] = "<p>Error: %s → %s</p>\n";
$err_fmt['mime'] = "<p>Error: %s → 非対応のMIMEタイプです。(%s)</p>\n";
$err_fmt['name'] = "<p>Error: %s → ファイル名の取得に失敗しました。</p>\n";
$err_fmt['size'] = "<p>Error: %s → 画像サイズの取得に失敗しました。</p>\n";
$err_fmt['pix']  = "<p>Error: %s → 画像が大きすぎます。(%s &times; %s, {$maxwidth} &times; {$maxheight} まで)</p>\n";
$err_fmt['dir']  = "<p>Error: %s → ディレクトリを作成できませんでした。(%s)</p>\n";
$err_fmt['move'] = "<p>Error: %s → リネーム失敗。(%s → %s)</p>\n";

// }}}
// {{{ process uploaded file

$upfiles = array();
if (!empty($_GET['upload']) && !empty($_FILES['upimg'])) {
    $errors = array_count_values($_FILES['upimg']['error']);
    if (!empty($errors[UPLOAD_ERR_NO_TMP_DIR])) {
        p2die('ImageCache2 - ファイルアップロード用のテンポラリフォルダがありません。');
    } elseif (count($_FILES['upimg']['error']) == $errors[UPLOAD_ERR_NO_FILE]) {
        P2Util::pushInfoHtml($err_fmt['none']);
    } else {
        // サムネイル作成クラスのインスタンスを作成
        $thumbnailer = new ImageCache2_Thumbnailer(ImageCache2_Thumbnailer::SIZE_DEFAULT);

        // DBに記録する共通データを設定
        $f_host = 'localhost';
        $f_time = time();
        $f_memo = isset($_POST['memo']) ? ImageCache2_Normalizer::normalize($_POST['memo'], 'CP932') : '';
        $f_rank = isset($_POST['rank']) ? intval($_POST['rank']) : 0;
        if ($f_rank > 5) {
            $f_rank = 5;
        } elseif ($f_rank < 0) {
            $f_rank = 0;
        }

        // アップロードされたファイルを処理
        foreach ($_FILES['upimg']['name'] as $key => $value) {
            $path     = $_POST['path'][$key];
            $name     = $_FILES['upimg']['name'][$key];
            $type     = $_FILES['upimg']['type'][$key];
            $filesize = $_FILES['upimg']['size'][$key];
            $tmpname  = $_FILES['upimg']['tmp_name'][$key];
            $errcode  = $_FILES['upimg']['error'][$key];

            if ($errcode == UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $file = ic2_check_uploaded_file($path, $name, $type, $filesize, $tmpname, $errcode);
            if (is_array($file)) {
                $upfiles[] = $file;
            } else {
                P2Util::pushInfoHtml($file);
            }
        }
    }
}

// }}}
// {{{ output

$_flexy_options = array(
    'locale' => 'ja',
    'charset' => 'Shift_JIS',
    'compileDir' => $_conf['compile_dir'] . DIRECTORY_SEPARATOR . 'ic2',
    'templateDir' => P2EX_LIB_DIR . '/ImageCache2/templates',
    'numberFormat' => '', // ",0,'.',','" と等価
);

$flexy = new HTML_Template_Flexy($_flexy_options);
$flexy->compile('ic2s.tpl.html');

if (!$isPopUp && (!empty($upfiles) || P2Util::hasInfoHtml())) {
    $showForm = false;
} else {
    $showForm = true;
}

// フォームを修正
$elements = $flexy->getElements();
if ($showForm) {
    $form_attr = array(
        'action' => $_SERVER['SCRIPT_NAME'] . '?upload=1',
        'accept-charset' => $_conf['accept_charset'],
    );
    $elements['fileupload']->setAttributes($form_attr);
    $elements['MAX_FILE_SIZE']->setValue($maxsize);
    $elements['popup']->setValue($isPopUp);
    if ($isPopUp) {
        $elements['fileupload']->setAttributes('target="_self"');
    } else {
        $elements['fileupload']->setAttributes('target="read"');
    }
}

// テンプレート変数
$view = new stdClass();
$view->php_self = $_SERVER['SCRIPT_NAME'];
$view->STYLE    = $STYLE;
$view->skin     = $skin_en;
$view->hint     = $_conf['detect_hint'];
$view->isPopUp  = $isPopUp;
$view->showForm = $showForm;
$view->info_msg = P2Util::getInfoHtml();
$view->upfiles  = $upfiles;
$view->maxfilesize = $maxsize_si;
$view->maxpostsize = ini_get('post_max_size');
$view->extra_headers   = $_conf['extra_headers_ht'];
$view->extra_headers_x = $_conf['extra_headers_xht'];

// ページを表示
P2Util::header_nocache();
$flexy->outputObject($view, $elements);

// }}}
// {{{ 関数
// {{{ ic2_check_uploaded_file()

/**
 * アップロードされた各画像ファイルを検証する。
 * 問題がなければ ic2_register_uploaded_file() にファイル情報を渡し、
 * 問題があればエラーメッセージ（文字列）を返す。
 */
function ic2_check_uploaded_file($path, $name, $type, $filesize, $tmpname, $errcode)
{
    global $_conf, $ini, $err_fmt;
    global $mimemap, $mimeregex, $maxsize, $maxwidth, $maxheight;

    $path_ht = p2h($path);

    // アップロード失敗のとき
    if ($errcode != UPLOAD_ERR_OK) {
        switch ($errcode) {
            case UPLOAD_ERR_INI_SIZE:
                $errmsg = 'アップロードされたファイルは、php.ini の upload_max_filesize ディレクティブの値を超えています。';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errmsg = 'アップロードされたファイルは、HTMLフォームで指定された MAX_FILE_SIZE を超えています。';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errmsg = 'アップロードされたファイルは一部のみしかアップロードされていません。';
                break;
            default:
                $errmsg = '原因不明のエラー';
        }
        return sprintf($err_fmt['file'], $path_ht, $errmsg);
    }

    // ブラウザから送信されたMIMEタイプを検証
    if (!preg_match($mimeregex, $type)) {
        return sprintf($err_fmt['mime'], $path_ht, $type);
    }

    // 正しい画像ファイルかどうかを確認
    $size = @getimagesize($tmpname);
    if (!$size || !$size[0] || !$size[1]) {
        return sprintf($err_fmt['size'], $path_ht);
    }

    // 縦横の大きさを確認
    if (($maxwidth > 0 && $size[0] > $maxwidth) || ($maxheight > 0 && $size[1] > $maxheight)) {
        return sprintf($err_fmt['pix'], $size[0], $size[1]);
    }

    // もう一度 MIMEタイプを検証
    $type = $size[2];
    if (!isset($mimemap[$type])) {
        $mime = isset($size['mime']) ? $size['mime'] : $type;
        return sprintf($err_fmt['mime'], $path_ht, $mime);
    }

    // ファイル名を取得
    $basename = p2_mb_basename($path);
    if ($basename == '') {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Mac') !== false) {
            $name = p2_combine_nfd_kana($name);
        }
        $name = mb_convert_encoding($name, 'CP932', 'UTF-8,CP51932,CP932');
        $basename = p2_mb_basename($name);
        if ($name == '') {
            return sprintf($err_fmt['name'], $path_ht);
        }
    }

    // ファイル情報を設定
    $file = array();
    $file['path'] = $path;
    $file['name'] = $basename;
    $file['size'] = $filesize;
    $file['mime'] = $mimemap[$type];
    $file['width']  = $size[0];
    $file['height'] = $size[1];
    $file['md5'] = md5_file($tmpname);
    $file['tmp_name'] = $tmpname;

    // DBに登録し、結果を返す
    return ic2_register_uploaded_file($file);
}

// }}}
// {{{ ic2_register_uploaded_file()

/**
 * アップロードされた画像ファイルをDBに登録する。
 * 成功したときはファイル情報（配列）を、
 * 失敗したときはエラーメッセージ（文字列）を返す。
 */
function ic2_register_uploaded_file($file)
{
    global $_conf, $ini, $err_fmt;
    global $thumbnailer;
    global $f_host, $f_time, $f_memo, $f_rank;

    $utf8_path = mb_convert_encoding($file['path'], 'UTF-8', 'CP932');
    $utf8_name = mb_convert_encoding($file['name'], 'UTF-8', 'CP932');
    $file['path'] = p2h($file['path']);
    $file['name'] = p2h($file['name']);
    $file['memo'] = $f_memo;
    $file['rank'] = $f_rank;
    $file['img_src'] = $thumbnailer->srcUrl($file['size'], $file['md5'], $file['mime']);
    $file['thumb'] = $thumbnailer->thumbUrl($file['size'], $file['md5'], $file['mime']);
    if (!file_exists($file['thumb'])) {
        $file['thumb'] = 'ic2.php?r=1&t=1&file=' . $file['size'] . '_' . $file['md5'];
    }
    if (preg_match('/(\d+)x(\d+)/', $thumbnailer->calc($file['width'], $file['height']), $thumb_xy)) {
        $file['thumb_x'] = $thumb_xy[1];
        $file['thumb_y'] = $thumb_xy[2];
    }

    // 既存の画像か検索
    $search1 = new ImageCache2_DataObject_Images();
    $search1->whereAddQuoted('size', '=', $file['size']);
    $search1->whereAddQuoted('md5',  '=', $file['md5']);
    $search1->whereAddQuoted('mime', '=', $file['mime']);

    $search2 = clone $search1;
    $search1->whereAddQuoted('uri',  '=', $utf8_path);

    // 全く同じ画像が登録されていたとき
    if ($search1->find(true)) {
        $update = clone $search1;
        $changed = false;
        if (strlen($f_memo) > 0 && strpos($search1->memo, $f_memo) === false){
            if (!is_null($search1->memo) && strlen($search1->memo) > 0) {
                $update->memo = $f_memo . ' ' . $search1->memo;
            } else {
                $update->memo = $f_memo;
            }
            $file['memo'] = mb_convert_encoding($update->memo, 'CP932', 'UTF-8');
            $changed = true;
        }
        if ($search1->rank != $f_rank) {
            $update->rank = $f_rank;
            $changed = true;
        }
        if ($changed) {
            $update->update();
        }
        $file['message'] = '<a href="iv2.php?field=md5&amp;keyword='
            . $file['md5'] . '" target="_blank">'
            . '同じ画像が登録されていました。</a>';
        if ($changed) {
            $file['message'] .= '(ステータスの更新あり)';
        }

    } else {

        $record = new ImageCache2_DataObject_Images();
        $record->uri    = $utf8_path;
        $record->host   = $f_host;
        $record->name   = $utf8_name;
        $record->size   = $file['size'];
        $record->md5    = $file['md5'];
        $record->width  = $file['width'];
        $record->height = $file['height'];
        $record->mime   = $file['mime'];
        $record->time   = $f_time;
        $record->rank   = $f_rank;
        if (strlen($f_memo) > 0) {
            $record->memo = $f_memo;
        }

        // 登録済みの画像で、URLが異なるとき
        if ($search2->find(true) && file_exists($file['img_src'])) {
            $record->insert();
            $file['message'] = '<a href="iv2.php?field=md5&amp;keyword='
                . $file['md5'] . '" target="_blank">'
                . '同じ画像が異なるURLで登録されていました。</a>';

        // 未登録の画像だったとき
        } else {
            $newdir = dirname($file['img_src']);
            if (!is_dir($newdir) && !@mkdir($newdir)) {
                return sprintf($err_fmt['dir'], $file['path'], $newdir);
            }
            if (!@move_uploaded_file($file['tmp_name'], $file['img_src'])) {
                return sprintf($err_fmt['file'], $file['path'], $file['tmp_name'], $file['img_src']);
            }
            $record->insert();
            $file['message'] = '<a href="iv2.php?field=md5&amp;keyword='
                . $file['md5'] . '" target="_blank">アップロード成功。</a>';
        }
    }

    return $file;
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
