<?php
/**
 * ImageCache2 - 画像のダウンロード・サムネイル作成
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
require_once 'HTTP/Client.php';
require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';

// 受け付けるMIMEタイプ
$mimemap = array('image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif');

// 設定ファイル読み込み
$ini = ic2_loadconfig();

// }}}
// {{{ prepare

// パラメータを設定
$id       = isset($_REQUEST['id'])    ? intval($_REQUEST['id']) : null;
$uri      = isset($_REQUEST['uri'])   ? $_REQUEST['uri'] : (isset($_REQUEST['url']) ? $_REQUEST['url'] : null);
$file     = isset($_REQUEST['file'])  ? $_REQUEST['file'] : null;
$force    = !empty($_REQUEST['f']);   // 強制更新
$thumb    = isset($_REQUEST['t'])     ? intval($_REQUEST['t']) : ImageCache2_Thumbnailer::SIZE_SOURCE;  // サムネイルタイプ
$dpr      = isset($_REQUEST['d'])     ? floatval($_REQUEST['d']) : 1.0; // device pixel ratio
$redirect = isset($_REQUEST['r'])     ? intval($_REQUEST['r']) : 1;     // 表示方法
$rank     = isset($_REQUEST['rank'])  ? intval($_REQUEST['rank']) : 0;  // レーティング
$memo     = (isset($_REQUEST['memo']) && strlen($_REQUEST['memo']) > 0) ? $_REQUEST['memo'] : null; // メモ
$referer  = (isset($_REQUEST['ref']) && strlen($_REQUEST['ref']) > 0)   ? $_REQUEST['ref']  : null; // リファラ

/*if (!isset($uri) && false !== ($url = getenv('PATH_INFO'))) {
    $uri = 'http:/' . $url;
}*/
if (empty($id) && empty($uri) && empty($file)) {
    ic2_error('x06', 'URLまたはファイル名がありません。', false);
}

if (!is_dir($_conf['tmp_dir'])) {
    FileCtl::mkdirRecursive($_conf['tmp_dir']);
}

if (!empty($uri)) {
    $uri = preg_replace('{^(https?://)ime\\.(?:nu|st)/}', '\\1', $uri);
    $pURL = @parse_url($uri);
    if (!$pURL || ($pURL['scheme'] != 'http' && $pURL['scheme'] != 'https') ||
        empty($pURL['host']) || empty($pURL['path']))
    {
        ic2_error('x06', '不正なURLです。', false);
    }

    // 強制あぼーんホストのとき
    if ($ini['Getter']['reject_hosts']) {
        $pattern = preg_quote($ini['Getter']['reject_hosts'], '/');
        $pattern = str_replace(',', '|', $pattern);
        $pattern = '/(' . $pattern . ')$/i';
        if (preg_match($pattern, $pURL['host'])) {
            ic2_error('x01', 'あぼーん対象ホストです。');
        }
    }

    // 強制あぼーんURLのとき
    if ($ini['Getter']['reject_regex']) {
        $pattern = str_replace('/', '\\/', $ini['Getter']['reject_regex']);
        $pattern = '/(' . $pattern . ')$/i';
        if (preg_match($pattern, $uri)) {
            ic2_error('x01', 'あぼーん対象URLです。');
        }
    }

    $doDL = true;
} else {
    if (isset($file) && !preg_match('/^(?P<size>[1-9][0-9]*)_(?P<md5>[0-9a-f]{32})(?:\.(?P<ext>jpg|png|gif))?$/', $file, $fdata)) {
        ic2_error('x06', '不正なファイル名です。', false);
    }
    $doDL = false;
}

// 値の調整
if (!in_array($thumb, array(ImageCache2_Thumbnailer::SIZE_SOURCE,
                            ImageCache2_Thumbnailer::SIZE_PC,
                            ImageCache2_Thumbnailer::SIZE_MOBILE,
                            ImageCache2_Thumbnailer::SIZE_INTERMD)))
{
    $thumb = ImageCache2_Thumbnailer::SIZE_DEFAULT;
}

if ($dpr === 1.5) {
    $thumb_type = $thumb | ImageCache2_Thumbnailer::DPR_1_5;
} elseif ($dpr === 2.0) {
    $thumb_type = $thumb | ImageCache2_Thumbnailer::DPR_2_0;
} else {
    $thumb_type = $thumb;
    $dpr = 1.0;
}

if ($rank < -1) {
    $rank = -1;
} elseif ($rank > 5) {
    $rank = 5;
}

if ($memo === '') {
    $memo = null;
}

$thumbnailer = new ImageCache2_Thumbnailer($thumb_type);

// }}}
// {{{ IC2TempFile

class IC2TempFile
{
    private $_filename = null;

    public function __construct($filename)
    {
        if (touch($filename)) {
            $this->_filename = realpath($filename);
        }
    }

    public function __destruct()
    {
        if ($this->_filename !== null) {
            if (file_exists($this->_filename)) {
                unlink($this->_filename);
            }
        }
    }
}

// }}}
// {{{ sleep

if ($doDL) {
    // 同じ画像のURIに対するクエリが（ほぼ）同時に発行されたときの重複GETを防ぐ
    // sleepした時間はプロセスの実行時間に含まれないので独自にタイマーを用意する（無限ループ回避）
    $dl_lock_file = $_conf['tmp_dir'] . DIRECTORY_SEPARATOR . 'ic2_lck_' . md5($uri);
    if (file_exists($dl_lock_file)) {
        $offtimer = ini_get('max_execution_time');
        if ($offtimer == 0) {
            $offtimer = 30;
        }
        while (file_exists($dl_lock_file)) {
            sleep(1); // 1秒停止
            $offtimer--;
            if ($offtimer < 0) {
                ic2_error(504);
            }
        }
    }

    // テンポラリファイルを作成、終了時に自動削除
    $dl_lock_obj = new IC2TempFile($dl_lock_file);
}

// }}}
// {{{ search

// 画像がキャッシュされているか確認
$search = new ImageCache2_DataObject_Images();
$filepath = null;
$fileurl = null;
$mtime = -1;
$retry = false;

if ($memo !== null) {
    $memo = ImageCache2_Normalizer::normalize($memo, 'CP932');
}

if ($doDL) {
    $result = $search->get($uri);
} else {
    if (isset($id)) {
        $search->whereAddQuoted('id', '=', $id);
    } else {
        $search->whereAddQuoted('size', '=', $fdata['size']);
        $search->whereAddQuoted('md5', '=', $fdata['md5']);
    }
    $result = $search->find(true);
    if (!$result) {
        ic2_error('404');
    }
    $force = false;
}

if ($result) {
    // ウィルススキャンにひっかかったファイルだったら終了。
    if (!$force && $search->mime == 'clamscan/infected') {
        ic2_error('x04', '', false);
    }
    // あぼーんフラグ（rankが負）が立っていたら終了。
    if (!$force && $search->rank < 0 && !isset($_REQUEST['rank'])) {
        ic2_error('x01', '', false);
    }
    $filepath = $thumbnailer->srcPath($search->size, $search->md5, $search->mime);
    $params = array('uri' => $search->uri, 'name' => $search->name, 'size' => $search->size,
                    'md5' => $search->md5, 'width' => $search->width, 'height' => $search->height,
                    'mime' => $search->mime, 'memo' => $search->memo, 'rank' => $search->rank);

    // 自動メモ機能が有効のとき
    if ($ini['General']['automemo'] && !is_null($memo) && strpos($search->memo, $memo) === false) {
        if (is_string($search->memo) && strlen($search->memo) > 0) {
            $memo .= ' ' . $search->memo;
        }
        $update = new ImageCache2_DataObject_Images();
        $update->memo = $params['memo'] = $memo;
        $update->whereAddQuoted('uri', '=', $search->uri);
        $update->update();
        unset($update);
    }

    // ランク変更
    if (isset($_REQUEST['rank'])) {
        $update = new ImageCache2_DataObject_Images();
        $update->rank = $params['rank'] = $rank;
        $update->whereAddQuoted('size', '=', $search->size);
        $update->whereAddQuoted('md5',  '=', $search->md5);
        $update->whereAddQuoted('mime', '=', $search->mime);
        $update->update();
        unset($update);
    }

    // ファイルが保存されていればそれでよし、保存されていなければレコードを削除する。
    if (file_exists($filepath)) {
        $fileurl = $thumbnailer->srcUrl($search->size, $search->md5, $search->mime);
        if ($force) {
            $_size = $search->size;
            $_md5  = $search->md5;
            $_mime = $search->mime;
            $mtime = $search->time;
        } else {
            ic2_finish($fileurl, $thumb, $params, false);
        }
    } else {
        $retry = true;
        $force = false;
        $_size = $search->size;
        $_md5  = $search->md5;
        $_mime = $search->mime;
    }
}

// 画像がブラックリストにあるか確認
$blacklist = new ImageCache2_DataObject_BlackList();
if ($blacklist->get($uri)) {
    switch ($blacklist->type) {
        case 0:
            $errcode = 'x05'; // お腹いっぱい
            break;
        case 1:
            $errcode = 'x01'; // あぼーん
            break;
        case 2:
            $errcode = 'x04'; // ウィルス感染
            break;
        default:
            $errcode = 'x06'; // ???
    }
    ic2_error($errcode, '', false);
}

// 画像がエラーログにあるか確認
if (!$force && $ini['Getter']['checkerror']) {
    $errlog = new ImageCache2_DataObject_Errors();
    if ($errlog->get($uri)) {
        ic2_error($errlog->errcode, '', false);
    }
}

// }}}
// {{{ init http-client

// 設定を確認
$conn_timeout = (isset($ini['Getter']['conn_timeout']) && $ini['Getter']['conn_timeout'] > 0)
    ? (float) $ini['Getter']['conn_timeout'] : 60.0;
$read_timeout = (isset($ini['Getter']['read_timeout']) && $ini['Getter']['read_timeout'] > 0)
    ? (int) $ini['Getter']['read_timeout'] : 60;
$ic2_ua = (!empty($_conf['expack.user_agent']))
    ? $_conf['expack.user_agent'] : $_SERVER['HTTP_USER_AGENT'];

// キャッシュされていなければ、取得を試みる
$client = new HTTP_Client();
$client->setRequestParameter('timeout', $conn_timeout);
$client->setRequestParameter('readTimeout', array($read_timeout, 0));
$client->setMaxRedirects(3);
$client->setDefaultHeader('User-Agent', $ic2_ua);
if ($mtime > 0) {
    $client->setDefaultHeader('If-Modified-Since', http_date($mtime));
}

// プロキシ設定
if ($ini['Proxy']['enabled'] && $ini['Proxy']['host'] && $ini['Proxy']['port']) {
    $client->setRequestParameter('proxy_host', $ini['Proxy']['host']);
    $client->setRequestParameter('proxy_port', $ini['Proxy']['port']);
    if ($ini['Proxy']['user']) {
        $client->setRequestParameter('proxy_user', $ini['Proxy']['user']);
        $client->setRequestParameter('proxy_pass', $ini['Proxy']['pass']);
        $proxy_auth_data = base64_encode($ini['Proxy']['user'] . ':' . $ini['Proxy']['pass']);
        $client->setDefaultHeader('Proxy-Authorization', 'Basic ' . $proxy_auth_data);
    }
}

// リファラ設定
if (is_null($referer)) {
    $send_referer = (boolean)$ini['Getter']['sendreferer'];
    if ($send_referer) {
        if ($ini['Getter']['norefhosts']) {
            $pattern = preg_quote($ini['Getter']['norefhosts'], '/');
            $pattern = str_replace(',', '|', $pattern);
            $pattern = '/' . $pattern . '/i';
            if (preg_match($pattern, $pURL['host'])) {
                $send_referer = false;
            }
        }
    } elseif ($ini['Getter']['refhosts']) {
        $pattern = preg_quote($ini['Getter']['refhosts'], '/');
        $pattern = str_replace(',', '|', $pattern);
        $pattern = '/' . $pattern . '/i';
        if (preg_match($pattern, $pURL['host'])) {
            $send_referer = true;
        }
    }
    if ($send_referer) {
        $referer = $uri . '.html';
    }
}

if (is_string($referer)) {
    $client->setDefaultHeader('Referer', $referer);
}

// }}}
// {{{ get

// ダウンロード
if ($ini['Getter']['retry_regex'] &&
    strlen(trim($ini['Getter']['retry_regex'])) > 0 &&
    intval($ini['Getter']['retry_max']) > 0 &&
    preg_match($ini['Getter']['retry_regex'], $uri))
{
    $retryCount = 0;
    do {
        $code = $client->get($uri);
        if ($code != 403) {
            break;
        }
        $retryCount++;
        sleep($ini['Getter']['retry_interval']);
    } while ($retryCount < intval($ini['Getter']['retry_max']));
} else {
    $code = $client->get($uri);
}

if (PEAR::isError($code)) {
    ic2_error('x02', $code->getMessage());
} elseif ($fileurl && $mtime > 0 && $code == 304) {
    // 304 Not Modified のとき
    ic2_finish($fileurl, $thumb, $params, false);
} elseif ($code != 200) {
    ic2_error($code);
}

$response = $client->currentResponse();
if (isset($response['headers']['content-type'])) {
    $serv_mime = $response['headers']['content-type'];
}

// 一時ファイルに保存
$tmpfile = tempnam($_conf['tmp_dir'], 'ic2_get_');
$tmpobj = new IC2TempFile($tmpfile);
$fp = fopen($tmpfile, 'wb');
if (!$fp) {
    ic2_error('x02', "fopen失敗。($tmpfile)");
}
fwrite($fp, $response['body']);
fclose($fp);

// }}}
// {{{ check

// ウィルススキャン
if ($ini['Getter']['virusscan']) {
    $searchpath = $thumbnailer->ini['Getter']['clamav'];
    if ($ini['Getter']['virusscan'] == 2) {
        $clamscan = 'clamdscan';
    } else {
        $clamscan = 'clamscan';
    }
    if ($clamscan = ic2_findexec($clamscan, $searchpath)) {
        $scan_command = $clamscan . ' --stdout ' . escapeshellarg(realpath($tmpfile));
        $scan_result  = @exec($scan_command, $scan_stdout, $scan_result);
        if ($scan_result == 1) {
            $params = array(
                'uri'    => $uri,
                'host'   => $pURL['host'],
                'name'   => basename($pURL['path']),
                'size'   => filesize($tmpfile),
                'md5'    => md5_file($tmpfile),
                'width'  => 0,
                'height' => 0,
                'mime' => 'clamscan/infected',
                'memo' => $memo
            );
            ic2_aborn($params, true);
            ic2_error('x04', 'ウィルスを発見しました。');
        }
    }
}

// 画像情報を調べる。MIMEタイプはサーバが送ってきたものを信頼しない。
$info = @getimagesize($tmpfile);
if (!$info) {
    ic2_error('x02', '画像サイズの取得に失敗しました。');
} elseif (!isset($info['mime'])) {
    // < PHP4.3.0
    ic2_error('x02', 'MIMEタイプの取得に失敗しました。');
} else {
    $mime = $info['mime'];
}
if (!in_array($mime, array_keys($mimemap))) {
    ic2_error('x02', "サポートされていないファイルタイプです。({$mime})");
}

// 正規の画像なら、ファイルサイズとMD5チェックサムを計算
$host = $pURL['host'];
$name = basename($pURL['path']);
$size = filesize($tmpfile);
$md5  = md5_file($tmpfile);
$width  = $info[0];
$height = $info[1];

// 強制更新を試みたものの、更新されていなかったとき（レスポンスコードは200）
if ($fileurl && $force && $mtime > 0 && $size == $_size && $md5 == $_md5 && $mime == $_mime) {
    ic2_finish($fileurl, $thumb, $params, false);
}

$params = compact('uri', 'host', 'name', 'size', 'md5', 'width', 'height', 'mime', 'memo');

// ファイルサイズが上限を越えていないか確認
ic2_checkSizeOvered($tmpfile, $params);

// 同じ画像があぼーんされているか確認
if (($check = ic2_checkAbornedFile($tmpfile, $params)) !== false) {
    $rank = $check;
}

// }}}
// {{{ finish

// すべてのチェックをパスしたなら、保存用の名前にリネームする
$newfile = $thumbnailer->srcPath($size, $md5, $mime);
$newurl = $thumbnailer->srcUrl($size, $md5, $mime);
$newdir = dirname($newfile);
if (!is_dir($newdir) && !@mkdir($newdir)) {
    ic2_error('x02', "ディレクトリを作成できませんでした。({$newdir})");
}
if (($force || !file_exists($newfile)) && !@rename($tmpfile, $newfile)) {
    ic2_error('x02', "リネーム失敗。({$tmpfile} → {$newfile})");
}
@chmod($newfile, 0644);

// データベースにファイル情報を記録する
$record = new ImageCache2_DataObject_Images();
if ($retry && $size == $_size && $md5 == $_md5 && $mime == $_mime) {
    $record->time = time();
    if ($ini['General']['automemo'] && !is_null($memo)) {
        $record->memo = $memo;
    }
    $record->whereAddQuoted('uri',  '=', $uri);
    $record->whereAddQuoted('size', '=', $size);
    $record->whereAddQuoted('md5',  '=', $md5);
    $record->whereAddQuoted('mime', '=', $mime);
    $record->update();
} else {
    $record->uri = $uri;
    $record->host = $host;
    $record->name = $name;
    $record->size = $size;
    $record->md5 = $md5;
    $record->width = $width;
    $record->height = $height;
    $record->mime = $mime;
    $record->time = time();
    $record->rank = $rank;
    if ($ini['General']['automemo'] && !is_null($memo)) {
        $record->memo = $memo;
    }
    $record->insert();
}

$is_anigif = false; $is_gif_caution == false;
if (($ini['Thumbdeco']['anigif'] || $ini['Thumbdeco']['gifcaution']) && $params['mime'] == 'image/gif') {
    $is_anigif = check_anigif($newfile);
    if ($ini['Thumbdeco']['gifcaution']) {
        $is_gif_caution = $is_anigif && check_mime_caution($serv_mime, $mime, $mimemap, $name);
    }
}
// 画像を表示
ic2_finish($newurl, $thumb, $params, $force, $ini['Thumbdeco']['anigif'] ? $is_anigif : false, $is_gif_caution);

// }}}
// {{{ 関数
// {{{ ic2_aborn()

function ic2_aborn($params, $infected = false)
{
    global $ini;
    extract($params);

    $aborn = new ImageCache2_DataObject_Images();
    $aborn->uri = $uri;
    $aborn->host = $host;
    $aborn->name = $name;
    $aborn->size = $size;
    $aborn->md5 = $md5;
    $aborn->width = $width;
    $aborn->height = $height;
    $aborn->mime = $mime;
    $aborn->time = time();
    $aborn->rank = $infected ? -4 : -1;
    if ($ini['General']['automemo'] && !is_null($memo)) {
        $aborn->memo = $memo;
    }
    return $aborn->insert();
}

// }}}
// {{{ ic2_checkAbornedFile()

function ic2_checkAbornedFile($tmpfile, $params)
{
    global $ini;
    extract($params);

    // ブラックリスト検索
    $bl_check = new ImageCache2_DataObject_BlackList();
    $bl_check->whereAddQuoted('size', '=', $size);
    $bl_check->whereAddQuoted('md5',  '=', $md5);
    if ($bl_check->find(true)) {
        $bl_add = clone $bl_check;
        $bl_add->id = null;
        $bl_add->uri = $uri;
        switch ((int)$bl_check->type) {
            case 0:
                $errcode = 'x05'; // No More
                break;
            case 1:
                $errcode = 'x01'; // Aborn
                break;
            case 2:
                $errcode = 'x04'; // Virus
                break;
            default:
                $errcode = 'x06'; // Unknown
        }
        // 厳密には、その可能性が限りなく高いだけで100%ではない
        ic2_error($errcode, 'ブラックリストにある画像と同じ内容です。', false);
    }

    // あぼーん画像検索
    $check = new ImageCache2_DataObject_Images();
    $check->whereAddQuoted('size', '=', $size);
    $check->whereAddQuoted('md5',  '=', $md5);
    //$check->whereAddQuoted('mime', '=', $mime); // SizeとMD5で十分
    // 同じのが異なるURLで複数登録されていて、ランクが違う可能性があるので
    // （普通に使う分には起こらない...と思う。少なくとも起こりにくいはず）
    $check->orderByArray(array('rank' => 'ASC'));
    if ($check->find(true)) {
        if ($check->rank < 0) {
            ic2_aborn($params);
            // 現状では（たぶんずっと） -1 or -4 だけだが、一応
            if ($check->rank >= -5) {
                $errcode = 'x0' . abs($check->rank);
            } else {
                $errcode = 'x06'; // Unknown
            }
            // 厳密には、以下同文
            if ($check->rank == -4) {
                $errmsg = 'ウィルスに感染していた画像と同じ内容です。';
            } else {
                $errmsg = '既にあぼーんされている画像と同じ内容です。';
            }
            ic2_error($errcode, $errmsg);
        } else {
            return $check->rank;
        }
    }

    return false;
}

// }}}
// {{{ ic2_checkSizeOvered()

function ic2_checkSizeOvered($tmpfile, $params)
{
    global $ini;
    extract($params);

    $isError = false;

    $maxsize = $ini['Source']['maxsize'];
    if (preg_match('/(\d+\.?\d*)([KMG])/i', $maxsize, $m)) {
        $maxsize = p2_si2int($m[1], $m[2]);
    } else {
        $maxsize = (int)$maxsize;
    }
    if (0 < $maxsize && $maxsize < $size) {
        $isError = true;
        $errmsg = "ファイルサイズが大きすぎます。(file:{$size}; max:{$maxsize};)";
    }

    $maxwidth = (int)$ini['Source']['maxwidth'] ;
    $maxheight = (int)$ini['Source']['maxheight'];
    if ((0 < $maxwidth && $maxwidth < $width) ||
        (0 < $maxheight && $maxheight < $height)
    ) {
        $isError = true;
        $errmsg = "画像サイズが大きすぎます。(file:{$width}x{$height}; max:{$maxwidth}x{$maxheight};)";
    }

    if ($isError) {
        ic2_aborn($params);
        ic2_error('x03', $errmsg);
    }

    return true;
}

// }}}
// {{{ ic2_display()

function ic2_display($url, $params)
{
    global $_conf, $ini, $thumb, $dpr, $redirect, $id, $uri, $file, $thumbnailer;

    if (strncmp($url, '/', 1) === 0) {
        $s = empty($_SERVER['HTTPS']) ? '' : 's';
        $to = 'http' . $s . '://' . $_SERVER['HTTP_HOST'] . $url;
    } else {
        $dir = dirname(P2Util::getMyUrl());
        if (strncasecmp($url, './', 2) == 0) {
            $to = $dir . substr($url, 1);
        } elseif (strncasecmp($url, '../', 3) == 0) {
            $to = dirname($dir) . substr($url, 2);
        } else {
            $to = $dir . '/' . $url;
        }
    }
    $name = basename($url);
    $ext = strrchr($name, '.');

    switch ($redirect) {
        case 1:
            header("Location: {$to}");
            exit;
        case 2:
            switch ($ext) {
                case '.jpg': header("Content-Type: image/jpeg; name=\"{$name}\""); break;
                case '.png': header("Content-Type: image/png; name=\"{$name}\""); break;
                case '.gif': header("Content-Type: image/gif; name=\"{$name}\""); break;
                default:
                    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false ||
                        strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false
                    ) {
                        header("Content-Type: application/octetstream; name=\"{$name}\"");
                    } else {
                        header("Content-Type: application/octet-stream; name=\"{$name}\"");
                    }
            }
            header("Content-Disposition: inline; filename=\"{$name}\"");
            header('Content-Length: ' . filesize($url));
            readfile($url);
            exit;
        default:
            if (!class_exists('HTML_Template_Flexy', false)) {
                require 'HTML/Template/Flexy.php';
            }
            if (!class_exists('HTML_QuickForm', false)) {
                require 'HTML/QuickForm.php';
            }
            if (!class_exists('HTML_QuickForm_Renderer_ObjectFlexy', false)) {
                require 'HTML/QuickForm/Renderer/ObjectFlexy.php';
            }

            if (isset($uri)) {
                $img_o = 'uri';
                $img_p = $uri;
            } elseif (isset($id)) {
                $img_o = 'id';
                $img_p = $id;
            } else {
                $img_o = 'file';
                $img_p = $file;
            }
            $img_q = $img_o . '=' . rawurlencode($img_p);

            // QuickFormの初期化
            $_size = explode('x', $thumbnailer->calc($params['width'], $params['height']));
            $_constants = array(
                'o' => sprintf('原寸 (%dx%d)', $params['width'], $params['height']),
                's' => '作成',
                't' => $thumb,
                'd' => $dpr,
                'u' => $img_p,
                'v' => $img_o,
                'x' => $_size[0],
                'y' => $_size[1],
            );
            $_defaults = array(
                'q' => $ini["Thumb{$thumb}"]['quality'],
                'r'  => '0',
            );
            $mobile = Net_UserAgent_Mobile::singleton();
            $qa = 'size=3 maxlength=3';
            if ($mobile->isDoCoMo()) {
                $qa .= ' istyle=4';
            } elseif ($mobile->isEZweb()) {
                $qa .= ' format=*N';
            } elseif ($mobile->isSoftBank()) {
                $qa .= ' mode=numeric';
            }
            $_presets = array('' => 'サイズ・品質');
            foreach ($ini['Dynamic']['presets'] as $_preset_name => $_preset_params) {
                $_presets[$_preset_name] = $_preset_name;
            }
            $qf = new HTML_QuickForm('imgmaker', 'get', 'ic2_mkthumb.php');
            $qf->setConstants($_constants);
            $qf->setDefaults($_defaults);
            $qf->addElement('hidden', 't');
            $qf->addElement('hidden', 'u');
            $qf->addElement('hidden', 'v');
            $qf->addElement('text', 'x', '高さ', $qa);
            $qf->addElement('text', 'y', '横幅', $qa);
            $qf->addElement('text', 'q', '品質', $qa);
            $qf->addElement('select', 'p', 'プリセット', $_presets);
            $qf->addElement('select', 'r', '回転', array('0' => 'なし', '90' => '右に90°', '270' => '左に90°', '180' => '180°'));
            $qf->addElement('checkbox', 'w', 'トリム');
            $qf->addElement('checkbox', 'z', 'DL');
            $qf->addElement('submit', 's');
            $qf->addElement('submit', 'o');

            // FlexyとQurickForm_Rendererの初期化
            $_flexy_options = array(
                'locale' => 'ja',
                'charset' => 'Shift_JIS',
                'compileDir' => $_conf['compile_dir'] . DIRECTORY_SEPARATOR . 'ic2',
                'templateDir' => P2EX_LIB_DIR . '/ImageCache2/templates',
                'numberFormat' => '', // ",0,'.',','" と等価
            );
            $flexy = new HTML_Template_Flexy($_flexy_options);
            $rdr = new HTML_QuickForm_Renderer_ObjectFlexy($flexy);
            $qf->accept($rdr);

            // 表示
            $flexy->setData('p2vid', P2_VERSION_ID);
            $flexy->setData('title', 'IC2::Cached');
            $flexy->setData('pc', !$_conf['ktai']);
            $flexy->setData('iphone', $_conf['iphone']);
            if (!$_conf['ktai']) {
                $flexy->setData('skin', $GLOBALS['skin_name']);
                //$flexy->setData('stylesheets', array('css'));
                //$flexy->setData('javascripts', array('js'));
            } else {
                $flexy->setData('k_color', array(
                    'c_bgcolor' => !empty($_conf['mobile.background_color']) ? $_conf['mobile.background_color'] : '#ffffff',
                    'c_text'    => !empty($_conf['mobile.text_color'])  ? $_conf['mobile.text_color']  : '#000000',
                    'c_link'    => !empty($_conf['mobile.link_color'])  ? $_conf['mobile.link_color']  : '#0000ff',
                    'c_vlink'   => !empty($_conf['mobile.vlink_color']) ? $_conf['mobile.vlink_color'] : '#9900ff',
                ));
            }

            $rank = isset($params['rank']) ? $params['rank'] : 0;
            if ($_conf['iphone']) {
                $img_dir = 'img/iphone/';
                $img_ext = '.png';
            } else {
                $img_dir = 'img/';
                $img_ext = $_conf['ktai'] ? '.gif' : '.png';
            }
            $stars = array();
            $stars[-1] = $img_dir . (($rank == -1) ? 'sn1' : 'sn0') . $img_ext;
            //$stars[0] = $img_dir . (($rank ==  0) ? 'sz1' : 'sz0') . $img_ext;
            $stars[0] = $img_dir . ($_conf['iphone'] ? 'sz0' : 'sz1') . $img_ext;
            for ($i = 1; $i <= 5; $i++) {
                $stars[$i] = $img_dir . (($rank >= $i) ? 's1' : 's0') . $img_ext;
            }

            $k_at_a = str_replace('&amp;', '&', $_conf['k_at_a']);
            $setrank_url = "ic2.php?{$img_q}&t={$thumb}&r=0{$k_at_a}";

            $flexy->setData('stars', $stars);
            $flexy->setData('params', $params);

            if ($thumb == 2 && $rank >= 0) {
                if ($ini['General']['inline'] == 1) {
                    $t = 2;
                    $link = null;
                } else {
                    $t = 1;
                    $link = $url;
                }
                $r = ($ini['General']['redirect'] == 1) ? 1 : 2;
                $preview = "{$_SERVER['SCRIPT_NAME']}?o=1&r={$r}&t={$t}&{$img_q}{$k_at_a}";
                $flexy->setData('preview', $preview);
                $flexy->setData('link', $link);
                $flexy->setData('info', null);
            } else {
                $flexy->setData('preview', null);
                $flexy->setData('link', $url);
                $flexy->setData('info', null);
            }

            if (!$_conf['ktai'] || $_conf['iphone']) {
                $flexy->setData('backto', null);
            } elseif (isset($_REQUEST['from'])) {
                $flexy->setData('backto', $_REQUEST['from']);
                $setrank_url .= '&from=' . rawurlencode($_REQUEST['from']);
            } elseif (isset($_SERVER['HTTP_REFERER'])) {
                $flexy->setData('backto', $_SERVER['HTTP_REFERER']);
            } else {
                $flexy->setData('backto', null);
            }

            $flexy->setData('stars', $stars);
            $flexy->setData('sertank', $setrank_url . '&rank=');

            if ($_conf['iphone']) {
                $_conf['extra_headers_ht'] .= <<<EOP
<link rel="stylesheet" type="text/css" href="css/ic2_iphone.css?{$_conf['p2_version_id']}">
EOP;
                $_conf['extra_headers_xht'] .= <<<EOP
<link rel="stylesheet" type="text/css" href="css/ic2_iphone.css?{$_conf['p2_version_id']}" />
EOP;
            }

            $flexy->setData('edit', (extension_loaded('gd') && $rank >= 0));
            $flexy->setData('form', $rdr->toObject());
            $flexy->setData('doctype', $_conf['doctype']);
            $flexy->setData('extra_headers',   $_conf['extra_headers_ht']);
            $flexy->setData('extra_headers_x', $_conf['extra_headers_xht']);
            $flexy->compile('preview.tpl.html');

            P2Util::header_nocache();
            $flexy->output();
    }
    exit;
}

// }}}
// {{{ ic2_error()

function ic2_error($code, $optmsg = '', $write_log = true)
{
    global $_conf, $id, $uri, $file, $redirect;

    $map = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        'x01' => 'IC2 - Aborned Image',
        'x02' => 'IC2 - Broken (or Not) Image',
        'x03' => 'IC2 - Too Large',
        'x04' => 'IC2 - Virus Infected',
        'x05' => 'IC2 - No More',
        'x06' => 'IC2 - ???',
    );

    $message = $code . ' ' . $map[$code];
    if ($optmsg) {
        $message .= '<br />' . $optmsg;
    }

    if ($write_log) {
        $logger = new ImageCache2_DataObject_Errors();
        $logger->uri     = isset($uri) ? $uri : (isset($id) ? $id : $file);
        $logger->errcode = $code;
        $logger->errmsg  = mb_convert_encoding($message, 'UTF-8', 'CP932');
        $logger->occured = time();
        $logger->insert();
        $logger->ic2_errlog_lotate();
    }

    /*if (isset($map[$code]) && 100 <= $code && $code <= 505) {
        header("HTTP/1.0 {$code} {$map[$code]}");
    }*/

    if ($redirect) {
        if ($_conf['ktai'] && !$_conf['iphone']) {
            $type = 'gif';
        } else {
            $type = 'png';
        }
        $img = strval($code) . '.' . $type;
        $path = './img/' . $img;
        $name = 'filename="' . $img . '"';
        header('Content-Type: image/' . $type . '; ' . $name);
        header('Content-Disposition: inline; ' . $name);
        readfile($path);
        exit;
    }
    echo <<<EOF
<html>
<head><title>ImageCache::Error</title></head>
<body>
<p>{$message}</p>
</body>
</html>
EOF;
    exit;
}

// }}}
// {{{ ic2_finish()

function ic2_finish($fileurl, $thumb, $params, $force, $anigif = false, $gif_caution = false)
{
    global $thumbnailer;

    extract($params);

    if ($thumb == 0) {
        ic2_display($fileurl, $params);
    } else {
        $thumburl = $thumbnailer->convert($size, $md5, $mime, $width, $height, $force, $anigif, $gif_caution);
        if (PEAR::isError($thumburl)) {
            ic2_error('x02', $thumburl->getMessage());
        }
        ic2_display($thumburl, $params);
    }
}

// }}}
// {{{ check_anigif()

function check_anigif($path) {
    require_once P2EX_LIB_DIR . '/ImageCache2/GifAnimationDetector.php';
    return GifAnimationDetector::isAnimated($path);
}
// }}}
// {{{ check_mime_caution()

function check_mime_caution($serv_mime, $file_mime, $mimemap, $name) {
    // サーバーの送ってきたcontent-typeが実際のファイルと異なっているか
    if (strlen($serv_mime) && strtolower($serv_mime) != strtolower($file_mime)) return true;
    // 念のため拡張子でもチェック
    $ext = strrchr($name, '.');
    if (strlen($ext) && $ext_mime = array_search($ext, $mimemap)) {
        if (strtolower($ext_mime) != strtolower($file_mime)) return true;
    }
    return false;
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
