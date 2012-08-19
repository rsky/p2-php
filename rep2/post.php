<?php
/**
 * rep2 - レス書き込み
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

if (!empty($_conf['disable_res'])) {
    p2die('書き込み機能は無効です。');
}

// 引数エラー
if (empty($_POST['host'])) {
    p2die('引数の指定が変です');
}

$el = error_reporting(E_ALL & ~E_NOTICE);
$salt = 'post' . $_POST['host'] . $_POST['bbs'] . $_POST['key'];
error_reporting($el);

if (!isset($_POST['csrfid']) or $_POST['csrfid'] != P2Util::getCsrfId($salt)) {
    p2die('不正なポストです');
}

if ($_conf['expack.aas.enabled'] && !empty($_POST['PREVIEW_AAS'])) {
    include P2_BASE_DIR . '/aas.php';
    exit;
}

//================================================================
// 変数
//================================================================
$newtime = date('gis');

$post_param_keys    = array('bbs', 'key', 'time', 'FROM', 'mail', 'MESSAGE', 'subject', 'submit');
$post_internal_keys = array('host', 'sub', 'popup', 'rescount', 'ttitle_en');
$post_optional_keys = array('newthread', 'beres', 'p2res', 'from_read_new', 'maru', 'csrfid');
$post_p2_flag_keys  = array('b', 'p2_post_confirm_cookie');

foreach ($post_param_keys as $pk) {
    ${$pk} = (isset($_POST[$pk])) ? $_POST[$pk] : '';
}
foreach ($post_internal_keys as $pk) {
    ${$pk} = (isset($_POST[$pk])) ? $_POST[$pk] : '';
}

if (!isset($ttitle)) {
    if ($ttitle_en) {
        $ttitle = UrlSafeBase64::decode($ttitle_en);
    } elseif ($subject) {
        $ttitle = $subject;
    } else {
        $ttitle = '';
    }
}

//$MESSAGE = rtrim($MESSAGE);

// {{{ ソースコードがきれいに再現されるように変換

if (!empty($_POST['fix_source'])) {
    // タブをスペースに
    $MESSAGE = tab2space($MESSAGE);
    // 特殊文字を実体参照に
    $MESSAGE = p2h($MESSAGE);
    // 自動URLリンク回避
    $MESSAGE = str_replace('tp://', 't&#112;://', $MESSAGE);
    // 行頭のスペースを実体参照に
    $MESSAGE = preg_replace('/^ /m', '&nbsp;', $MESSAGE);
    // 二つ続くスペースの一つ目を実体参照に
    $MESSAGE = preg_replace('/(?<!&nbsp;)  /', '&nbsp; ', $MESSAGE);
    // 奇数回スペースがくり返すときの仕上げ
    $MESSAGE = preg_replace('/(?<=&nbsp;)  /', ' &nbsp;', $MESSAGE);
}

// }}}

// したらばのlivedoor移転に対応。post先をlivedoorとする。
$host = P2Util::adjustHostJbbs($host);

// machibbs、JBBS@したらば なら
if (P2Util::isHostMachiBbs($host) or P2Util::isHostJbbsShitaraba($host)) {
    $bbs_cgi = '/bbs/write.cgi';

    // JBBS@したらば なら
    if (P2Util::isHostJbbsShitaraba($host)) {
        $bbs_cgi = '../../bbs/write.cgi';
        preg_match('/\\/(\\w+)$/', $host, $ar);
        $dir = $ar[1];
        $dir_k = 'DIR';
    }

    /* compact() と array_combine() でPOSTする値の配列を作るので、
       $post_param_keys と $post_send_keys の値の順序は揃える！ */
    //$post_param_keys  = array('bbs', 'key', 'time', 'FROM', 'mail', 'MESSAGE', 'subject', 'submit');
    $post_send_keys     = array('BBS', 'KEY', 'TIME', 'NAME', 'MAIL', 'MESSAGE', 'SUBJECT', 'submit');
    $key_k     = 'KEY';
    $subject_k = 'SUBJECT';

// 2ch
} else {
    if ($sub) {
        $bbs_cgi = "/test/{$sub}bbs.cgi";
    } else {
        $bbs_cgi = '/test/bbs.cgi';
    }
    $post_send_keys = $post_param_keys;
    $key_k     = 'key';
    $subject_k = 'subject';
}

// submit は書き込むで固定してしまう（Beで書き込むの場合もあるため）
$submit = '書き込む';

$post = array_combine($post_send_keys, compact($post_param_keys));
$post_cache = $post;
unset($post_cache['submit']);

if (!empty($_POST['newthread'])) {
    unset($post[$key_k]);
    $location_ht = "{$_conf['subject_php']}?host={$host}&amp;bbs={$bbs}{$_conf['k_at_a']}";
} else {
    unset($post[$subject_k]);
    $location_ht = "{$_conf['read_php']}?host={$host}&amp;bbs={$bbs}&amp;key={$key}&amp;ls={$rescount}-&amp;refresh=1&amp;nt={$newtime}{$_conf['k_at_a']}";
    if (!$_conf['iphone']) {
        $location_ht .= "#r{$rescount}";
    }
}

if (!empty($_POST['savedraft'])) {
    // 書き込みを一時的に保存
    $post_backup_key = PostDataStore::getKeyForBackup($host, $bbs, $key, !empty($_REQUEST['newthread']));
    PostDataStore::set($post_backup_key, $post_cache);
    $reload = empty($_POST['from_read_new']);
    showPostMsg(false, '下書きを保存しました', $reload);
    exit;
}

if (P2Util::isHostJbbsShitaraba($host)) {
    $post[$dir_k] = $dir;
}

// {{{ 2chで●ログイン中ならsid追加

if (!empty($_POST['maru']) and P2Util::isHost2chs($host) && file_exists($_conf['sid2ch_php'])) {

    // ログイン後、24時間以上経過していたら自動再ログイン
    if (file_exists($_conf['idpw2ch_php']) && filemtime($_conf['sid2ch_php']) < time() - 60*60*24) {
        require_once P2_LIB_DIR . '/login2ch.inc.php';
        login2ch();
    }

    include $_conf['sid2ch_php'];
    $post['sid'] = $SID2ch;
}

// }}}

if (!empty($_POST['p2_post_confirm_cookie'])) {
    $post_ignore_keys = array_merge($post_param_keys, $post_internal_keys, $post_optional_keys, $post_p2_flag_keys);
    foreach ($_POST as $k => $v) {
        if (!array_key_exists($k, $post) && !in_array($k, $post_ignore_keys)) {
            $post[$k] = $v;
        }
    }
}

if (!empty($_POST['newthread'])) {
    $ptitle = 'rep2 - 新規スレッド作成';
} else {
    $ptitle = 'rep2 - レス書き込み';
}

$post_backup_key = PostDataStore::getKeyForBackup($host, $bbs, $key, !empty($_REQUEST['newthread']));
$post_config_key = PostDataStore::getKeyForConfig($host, $bbs);

// 設定を保存
PostDataStore::set($post_config_key, array(
    'beres' => !empty($_REQUEST['beres']),
    'p2res' => !empty($_REQUEST['p2res']),
));

//================================================================
// 書き込み処理
//================================================================

// 書き込みを一時的に保存
PostDataStore::set($post_backup_key, $post_cache);

// ポスト実行
if (!empty($_POST['p2res']) && empty($_POST['newthread'])) {
    // 公式p2で書き込み
    $posted = postIt2($host, $bbs, $key, $FROM, $mail, $MESSAGE);
} else {
    // cookie 読み込み
    $cookie_key = $_login->user_u . '/' . P2Util::normalizeHostName(P2Util::isHostBbsPink($host) ? 'www.bbspink.com' : P2Util::isHost2chs($host) ? 'www.2ch.net' : $host); // 忍法帳対応
    if ($p2cookies = CookieDataStore::get($cookie_key)) {
        if (is_array($p2cookies)) {
            if (array_key_exists('expires', $p2cookies)) {
                // 期限切れなら破棄
                if (time() > strtotime($p2cookies['expires'])) {
                    CookieDataStore::delete($cookie_key);
                    $p2cookies = null;
                }
            }
        } else {
            CookieDataStore::delete($cookie_key);
            $p2cookies = null;
        }
    } else {
        $p2cookies = null;
    }

    // 直接書き込み
    $posted = postIt($host, $bbs, $key, $post);

    // cookie 保存
    if ($p2cookies) {
        CookieDataStore::set($cookie_key, $p2cookies);
    }
}

// 投稿失敗記録を削除
if ($posted) {
    PostDataStore::delete($post_backup_key);
}

//=============================================
// スレ立て成功なら、subjectからkeyを取得
//=============================================
if (!empty($_POST['newthread']) && $posted) {
    sleep(1);
    $key = getKeyInSubject();
}

//=============================================
// key.idx 保存
//=============================================
// <> を外す。。
$tag_rec['FROM'] = str_replace('<>', '', $FROM);
$tag_rec['mail'] = str_replace('<>', '', $mail);

// 名前とメール、空白時は P2NULL を記録
$tag_rec_n['FROM'] = ($tag_rec['FROM'] == '') ? 'P2NULL' : $tag_rec['FROM'];
$tag_rec_n['mail'] = ($tag_rec['mail'] == '') ? 'P2NULL' : $tag_rec['mail'];

if ($host && $bbs && $key) {
    $keyidx = P2Util::idxDirOfHostBbs($host, $bbs) . $key . '.idx';

    // 読み込み
    if ($keylines = FileCtl::file_read_lines($keyidx, FILE_IGNORE_NEW_LINES)) {
        $akeyline = explode('<>', $keylines[0]);
    }
    $sar = array($akeyline[0], $akeyline[1], $akeyline[2], $akeyline[3], $akeyline[4],
                 $akeyline[5], $akeyline[6], $tag_rec_n['FROM'], $tag_rec_n['mail'], $akeyline[9],
                 $akeyline[10], $akeyline[11], $akeyline[12]);
    P2Util::recKeyIdx($keyidx, $sar); // key.idxに記録
}

//=============================================
// 書き込み履歴
//=============================================
if (empty($posted)) {
    exit;
}

if ($host && $bbs && $key) {

    $lock = new P2Lock($_conf['res_hist_idx'], false);

    FileCtl::make_datafile($_conf['res_hist_idx']); // なければ生成

    $lines = FileCtl::file_read_lines($_conf['res_hist_idx'], FILE_IGNORE_NEW_LINES);

    $neolines = array();

    // {{{ 最初に重複要素を削除しておく

    if (is_array($lines)) {
        foreach ($lines as $line) {
            $lar = explode('<>', $line);
            // 重複回避, keyのないものは不正データ
            if (!$lar[1] || $lar[1] == $key) {
                continue;
            } 
            $neolines[] = $line;
        }
    }

    // }}}

    // 新規データ追加
    $newdata = "{$ttitle}<>{$key}<><><><><><>{$tag_rec['FROM']}<>{$tag_rec['mail']}<><>{$host}<>{$bbs}";
    array_unshift($neolines, $newdata);
    while (sizeof($neolines) > $_conf['res_hist_rec_num']) {
        array_pop($neolines);
    }

    // {{{ 書き込む

    if ($neolines) {
        $cont = '';
        foreach ($neolines as $l) {
            $cont .= $l . "\n";
        }

        if (FileCtl::file_write_contents($_conf['res_hist_idx'], $cont) === false) {
            p2die('cannot write file.');
        }
    }

    // }}}

    $lock->free();
}

//=============================================
// 書き込みログ記録
//=============================================
if ($_conf['res_write_rec']) {

    // データPHP形式（p2_res_hist.dat.php, タブ区切り）の書き込み履歴を、dat形式（p2_res_hist.dat, <>区切り）に変換する
    P2Util::transResHistLogPhpToDat();

    $date_and_id = date('y/m/d H:i');
    $message = htmlspecialchars($MESSAGE, ENT_NOQUOTES, 'Shift_JIS');
    $message = preg_replace('/\\r\\n|\\r|\\n/', '<br>', $message);

    FileCtl::make_datafile($_conf['res_hist_dat']); // なければ生成

    $resnum = '';
    if (!empty($_POST['newthread'])) {
        $resnum = 1;
    } else {
        if ($rescount) {
            $resnum = $rescount + 1;
        }
    }

    // 新規データ
    $newdata = "{$tag_rec['FROM']}<>{$tag_rec['mail']}<>{$date_and_id}<>{$message}<>{$ttitle}<>{$host}<>{$bbs}<>{$key}<>{$resnum}";

    // まずタブを全て外して（2chの書き込みではタブは削除される 2004/12/13）
    $newdata = str_replace("\t", '', $newdata);
    // <>をタブに変換して
    //$newdata = str_replace('<>', "\t", $newdata);

    $cont = $newdata."\n";

    // 書き込み処理
    if (FileCtl::file_write_contents($_conf['res_hist_dat'], $cont, FILE_APPEND) === false) {
        trigger_error('rep2 error: 書き込みログの保存に失敗しました', E_USER_WARNING);
        // これは実際は表示されないけれども
        //P2Util::pushInfoHtml('<p>rep2 error: 書き込みログの保存に失敗しました</p>');
    }
}

//===========================================================
// 関数
//===========================================================
// {{{ postIt()

/**
 * レスを書き込む
 *
 * @return boolean 書き込み成功なら true、失敗なら false
 */
function postIt($host, $bbs, $key, $post)
{
    global $_conf, $post_result, $post_error2ch, $p2cookies, $popup, $rescount, $ttitle_en;
    global $bbs_cgi;

    $method = 'POST';
    $bbs_cgi_url = 'http://' . $host . $bbs_cgi;

    $URL = parse_url($bbs_cgi_url); // URL分解
    if (isset($URL['query'])) { // クエリー
        $URL['query'] = '?' . $URL['query'];
    } else {
        $URL['query'] = '';
    }

    // プロキシ
    if ($_conf['proxy_use']) {
        $send_host = $_conf['proxy_host'];
        $send_port = $_conf['proxy_port'];
        $send_path = $bbs_cgi_url;
    } else {
        $send_host = $URL['host'];
        $send_port = isset($URL['port']) ? $URL['port'] : 80;
        $send_path = $URL['path'] . $URL['query'];
    }

    if (!$send_port) { $send_port = 80; }    // デフォルトを80

    $request = "{$method} {$send_path} HTTP/1.0\r\n";
    $request .= "Host: {$URL['host']}\r\n";
    $request .= "User-Agent: Monazilla/1.00 ({$_conf['p2ua']})\r\n";
    $request .= "Referer: http://{$URL['host']}/\r\n";

    // クッキー
    $cookies_to_send = '';
    if ($p2cookies) {
        foreach ($p2cookies as $cname => $cvalue) {
            if ($cname != 'expires') {
                $cookies_to_send .= " {$cname}={$cvalue};";
            }
        }
    }

    // be.2ch.net 認証クッキー
    if (P2Util::isHostBe2chNet($host) || !empty($_REQUEST['beres'])) {
        $cookies_to_send .= ' MDMD='.$_conf['be_2ch_code'].';';    // be.2ch.netの認証コード(パスワードではない)
        $cookies_to_send .= ' DMDM='.$_conf['be_2ch_mail'].';';    // be.2ch.netの登録メールアドレス
    }

    if (!$cookies_to_send) { $cookies_to_send = ' ;'; }
    $request .= 'Cookie:'.$cookies_to_send."\r\n";
    //$request .= 'Cookie: PON='.$SPID.'; NAME='.$FROM.'; MAIL='.$mail."\r\n";

    $request .= "Connection: Close\r\n";

    // {{{ POSTの時はヘッダを追加して末尾にURLエンコードしたデータを添付

    if (strcasecmp($method, 'POST') == 0) {
        $post_enc = array();
        while (list($name, $value) = each($post)) {

            // したらば or be.2ch.netなら、EUCに変換
            if (P2Util::isHostJbbsShitaraba($host) || P2Util::isHostBe2chNet($host)) {
                $value = mb_convert_encoding($value, 'CP51932', 'CP932');
            }

            $post_enc[] = $name . '=' . rawurlencode($value);
        }
        $postdata = implode("&", $post_enc);
        $request .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $request .= "Content-Length: ".strlen($postdata)."\r\n";
        $request .= "\r\n";
        $request .= $postdata;

    } else {
        $request .= "\r\n";
    }
    // }}}

    // WEBサーバへ接続
    $fp = fsockopen($send_host, $send_port, $errno, $errstr, $_conf['http_conn_timeout']);
    if (!$fp) {
        $errstr = p2h($errstr);
        showPostMsg(false, "サーバ接続エラー: $errstr ($errno)<br>p2 Error: 板サーバへの接続に失敗しました", false);
        return false;
    }
    stream_set_timeout($fp, $_conf['http_read_timeout'], 0);

    //echo '<h4>$request</h4><p>' . $request . "</p>"; //for debug
    fputs($fp, $request);

    $start_here = false;
    $post_seikou = false;

    while (!p2_stream_eof($fp, $timed_out)) {

        if ($start_here) {
            $wr = '';
            while (!p2_stream_eof($fp, $timed_out)) {
                $wr .= fread($fp, 164000);
            }
            $response = $wr;
            break;

        } else {
            $l = fgets($fp, 164000);
            //echo $l .'<br>'; // for debug
            // クッキーキタ
            if (preg_match('/Set-Cookie: (.+?)\\r\\n/', $l, $matches)) {
                //echo '<p>' . $matches[0] . '</p>'; //
                $cgroups = explode(';', $matches[1]);
                if ($cgroups) {
                    foreach ($cgroups as $v) {
                        if (preg_match('/(.+)=(.*)/', $v, $m)) {
                            $k = ltrim($m[1]);
                            if ($k != 'path') {
                                if (!$p2cookies) {
                                    $p2cookies = array();
                                }
                                $p2cookies[$k] = $m[2];
                            }
                        }
                    }
                }
                if ($p2cookies) {
                    $cookies_to_send = '';
                    foreach ($p2cookies as $cname => $cvalue) {
                        if ($cname != 'expires') {
                            $cookies_to_send .= " {$cname}={$cvalue};";
                        }
                    }
                    $newcookies = "Cookie:{$cookies_to_send}\r\n";

                    $request = preg_replace('/Cookie: .*?\\r\\n/', $newcookies, $request);
                }

            // 転送は書き込み成功と判断
            } elseif (preg_match('/^Location: /', $l, $matches)) {
                $post_seikou = true;
            }
            if ($l == "\r\n") {
                $start_here = true;
            }
        }

    }
    fclose($fp);

    // be.2ch.net or JBBSしたらば 文字コード変換 EUC→SJIS
    if (P2Util::isHostBe2chNet($host) || P2Util::isHostJbbsShitaraba($host)) {
        $response = mb_convert_encoding($response, 'CP932', 'CP51932');

        //<META http-equiv="Content-Type" content="text/html; charset=EUC-JP">
        $response = preg_replace(
            '{<head>(.*?)<META http-equiv="Content-Type" content="text/html; charset=EUC-JP">(.*)</head>}is',
            '<head><meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">$1$2</head>',
            $response);
    }

    $kakikonda_match = '{<title>.*(?:書きこみました|■ 書き込みました ■|書き込み終了 - SubAll BBS).*</title>}is';
    $cookie_kakunin_match = '{<!-- 2ch_X:cookie -->|<title>■ 書き込み確認 ■</title>|>書き込み確認。<}';

    if (preg_match('/<.+>/s', $response, $matches)) {
        $response = $matches[0];
    }

    // カキコミ成功
    if ($post_seikou || preg_match($kakikonda_match, $response)) {
        $reload = empty($_POST['from_read_new']);
        showPostMsg(true, '書きこみが終わりました。', $reload);

        // +Wiki sambaタイマー
        if ($_conf['wiki.samba_timer']) {
            require_once P2_LIB_DIR . '/wiki/Samba.php';
            $samba = new Samba();
            $samba->setWriteTime($host, $bbs);
            $samba->save();
        }

        return true;
        //$response_ht = p2h($response);
        //echo "<pre>{$response_ht}</pre>";

    // cookie確認（post再チャレンジ）
    } elseif (preg_match($cookie_kakunin_match, $response)) {
        showCookieConfirmation($host, $response);
        return false;

    // その他はレスポンスをそのまま表示
    } else {
        echo preg_replace('@こちらでリロードしてください。<a href="\\.\\./[a-z]+/index\\.html"> GO! </a><br>@', '', $response);
        return false;
    }
}

// }}}
// {{{ postIt2()

/**
 * 公式p2でレスを書き込む
 *
 * @return boolean 書き込み成功なら true、失敗なら false
 */
function postIt2($host, $bbs, $key, $FROM, $mail, $MESSAGE)
{
    if (P2Util::isHostBe2chNet($host) || !empty($_REQUEST['beres'])) {
        $beRes = true;
    } else {
        $beRes = false;
    }

    try {
        $posted = P2Util::getP2Client()->post($host, $bbs, $key,
                                              $FROM, $mail, $MESSAGE,
                                              $beRes, $response);
    } catch (P2Exception $e) {
        p2die('公式p2ポスト失敗', $e->getMessage());
    }

    if ($posted) {
        $reload = empty($_POST['from_read_new']);
        showPostMsg(true, '書きこみが終わりました。', $reload);
    } else {
        $result_msg = '公式p2ポスト失敗</p>'
                    . '<pre>' . p2h($response['body']) . '</pre>'
                    . '<p>-';
        showPostMsg(false, $result_msg, false);
    }

    return $posted;
}

// }}}
// {{{ showPostMsg()

/**
 * 書き込み処理結果表示する
 *
 * @return void
 */
function showPostMsg($isDone, $result_msg, $reload)
{
    global $_conf, $location_ht, $popup, $ttitle, $ptitle;
    global $STYLE, $skin_en;

    // プリント用変数 ===============
    if (!$_conf['ktai']) {
        $class_ttitle = ' class="thre_title"';
    } else {
        $class_ttitle = '';
    }
    $ttitle_ht = "<b{$class_ttitle}>{$ttitle}</b>";

    $popup_ht = '';
    if ($isDone) {
        // 2005/03/01 aki: jigブラウザに対応するため、&amp; ではなく & で
        // 2005/04/25 rsk: <script>タグ内もCDATAとして扱われるため、&amp;にしてはいけない
        $location_noenc = str_replace('&amp;', '&', $location_ht);
        if ($popup) {
            $popup_ht = <<<EOJS
<script type="text/javascript">
//<![CDATA[
    opener.location.href = "{$location_noenc}";
    var delay= 3*1000;
    setTimeout("window.close()", delay);
//]]>
</script>
EOJS;
        } else {
            $_conf['extra_headers_ht'] .= <<<EOP
<meta http-equiv="refresh" content="1;URL={$location_noenc}">
EOP;
        }
    }

    // プリント ==============
    echo $_conf['doctype'];
    echo <<<EOHEADER
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
EOHEADER;

    if ($isDone) {
        echo "    <title>rep2 - 書きこみました。</title>";
    } else {
        echo "    <title>{$ptitle}</title>";
    }

    if (!$_conf['ktai']) {
        echo <<<EOP
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=post&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">\n
EOP;
        if ($popup) {
            echo <<<EOSCRIPT
            <script type="text/javascript">
            //<![CDATA[
                resizeTo({$STYLE['post_pop_size']});
            //]]>
            </script>
EOSCRIPT;
        }
        if ($reload) {
            echo $popup_ht;
        }
        $kakunin_ht = '';
    } else {
        $kakunin_ht = <<<EOP
<p><a href="{$location_ht}">確認</a></p>
EOP;
    }

    echo "</head>\n";
    echo "<body{$_conf['k_colors']}>\n";

    P2Util::printInfoHtml();

    echo <<<EOP
<p>{$ttitle_ht}</p>
<p>{$result_msg}</p>
{$kakunin_ht}
</body>
</html>
EOP;
}

// }}}
// {{{ showCookieConfirmation()

/**
 * Cookie確認HTMLを表示する
 *
 * @param   string $host        ホスト名
 * @param   string $response    レスポンスボディ
 * @return  void
 */
function showCookieConfirmation($host, $response)
{
    global $_conf, $post_param_keys, $post_send_keys, $post_optional_keys;
    global $popup, $rescount, $ttitle_en;
    global $STYLE, $skin_en;

    // HTMLをDOMで解析
    $doc = P2Util::getHtmlDom($response, 'Shift_JIS', false);
    if (!$doc) {
        showUnexpectedResponse($response, __LINE__);
        return;
    }

    $xpath = new DOMXPath($doc);
    $heads = $doc->getElementsByTagName('head');
    $bodies = $doc->getElementsByTagName('body');
    if ($heads->length != 1 || $bodies->length != 1) {
        showUnexpectedResponse($response, __LINE__);
        return;
    }

    $head = $heads->item(0);
    $body = $bodies->item(0);
    $xpath = new DOMXPath($doc);

    // フォームを探索
    $forms = $xpath->query(".//form[(@method = 'POST' or @method = 'post')
            and (starts-with(@action, '../test/bbs.cgi') or starts-with(@action, '../test/subbbs.cgi'))]", $body);
    if ($forms->length != 1) {
        showUnexpectedResponse($response, __LINE__);
        return;
    }
    $form = $forms->item(0);

    if (!preg_match('{^\\.\\./test/(sub)?bbs\\.cgi(?:\\?guid=ON)?$}', $form->getAttribute('action'), $matches)) {
        showUnexpectedResponse($response, __LINE__);
        return;
    }

    if (array_key_exists(1, $matches) && strlen($matches[1])) {
        $subbbs = $matches[1];
    } else {
        $subbbs = false;
    }

    // form要素の属性値を書き換える
    // method属性とaction属性以外の属性は削除し、accept-charset属性を追加する
    // DOMNamedNodeMapのイテレーションと、それに含まれるノードの削除は別に行う
    $rmattrs = array();
    foreach ($form->attributes as $name => $node) {
        switch ($name) {
            case 'method':
                //$node->value = 'POST';
                break;
            case 'action':
                $node->value = './post.php';
                break;
            default:
                $rmattrs[] = $name;
        }
    }
    foreach ($rmattrs as $name) {
        $form->removeAttribute($name);
    }
    $form->setAttribute('accept-charset', $_conf['accept_charset']);

    // POSTする値を再設定
    foreach (array_combine($post_send_keys, $post_param_keys) as $key => $name) {
        if (array_key_exists($name, $_POST)) {
            $nodes = $xpath->query("./input[@type = 'hidden' and @name = '{$key}']");
            if ($nodes->length) {
                $elem = $nodes->item(0);
                if ($key != $name) {
                    $elem->setAttribute('name', $name);
                }
                $elem->setAttribute('value', mb_convert_encoding($_POST[$name], 'UTF-8', 'CP932'));
            }
        }
    }

    // 各種隠しパラメータを追加
    $hidden = $doc->createElement('input');
    $hidden->setAttribute('type', 'hidden');

    // rep2が使用する変数その1
    foreach (array('host', 'popup', 'rescount', 'ttitle_en') as $name) {
        $elem = $hidden->cloneNode();
        $elem->setAttribute('name', $name);
        $elem->setAttribute('value', $$name);
        $form->appendChild($elem);
    }

    // rep2が使用する変数その2
    foreach ($post_optional_keys as $name) {
        if (array_key_exists($name, $_POST)) {
            $elem = $hidden->cloneNode();
            $elem->setAttribute('name', $name);
            $elem->setAttribute('value', mb_convert_encoding($_POST[$name], 'UTF-8', 'CP932'));
            $form->appendChild($elem);
        }
    }

    // POST先がsubbbs.cgi
    if ($subbbs !== false) {
        $elem = $hidden->cloneNode();
        $elem->setAttribute('name', 'sub');
        $elem->setAttribute('value', $subbbs);
        $form->appendChild($elem);
    }

    // ソースコード補正
    if (!empty($_POST['fix_source'])) {
        $elem = $hidden->cloneNode();
        $elem->setAttribute('name', 'fix_source');
        $elem->setAttribute('value', '1');
        $form->appendChild($elem);
    }

    // 強制ビュー指定
    if ($_conf['b'] != $_conf['client_type']) {
        $elem = $hidden->cloneNode();
        $elem->setAttribute('name', 'b');
        $elem->setAttribute('value', $_conf['b']);
        $form->appendChild($elem);
    }

    // Cookie確認フラグ
    $elem = $hidden->cloneNode();
    $elem->setAttribute('name', 'p2_post_confirm_cookie');
    $elem->setAttribute('value', '1');
    $form->appendChild($elem);

    // エンコーディング判定のヒント
    $hidden->setAttribute('name', '_hint');
    $hidden->setAttribute('value', mb_convert_encoding($_conf['detect_hint'], 'UTF-8', 'CP932'));
    $form->insertBefore($hidden, $form->firstChild);

    // ヘッダに要素を追加
    if (!$_conf['ktai']) {
        $skin_q = str_replace('&amp;', '&', $skin_en);
        $link = $doc->createElement('link');
        $link->setAttribute('rel', 'stylesheet');
        $link->setAttribute('type', 'text/css');
        $link->setAttribute('href', "css.php?css=style&skin={$skin_q}");
        $link = $head->appendChild($link)->cloneNode();
        $link->setAttribute('href', "css.php?css=post&skin={$skin_q}");
        $head->appendChild($link);

        if ($popup) {
            $mado_okisa = explode(',', $STYLE['post_pop_size']);
            $script = $doc->createElement('script');
            $script->setAttribute('type', 'text/javascript');
            $head->appendChild($script)->appendChild($doc->createCDATASection(
                sprintf('resizeTo(%d,%d);', $mado_okisa[0], $mado_okisa[1] + 200)
            ));
        }
    }

    // 構文修正
    // li要素を直接の子要素として含まないul要素をblockquote要素で置換
    // DOMNodeListのイテレーションと、それに含まれるノードの削除は別に行う
    $nodes = array();
    foreach ($xpath->query('.//ul[count(./li)=0]', $body) as $node) {
        $nodes[] = $node;
    }
    foreach ($nodes as $node) {
        $children = array();
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        $elem = $doc->createElement('blockquote');
        foreach ($children as $child) {
            $elem->appendChild($node->removeChild($child));
        }
        $node->parentNode->replaceChild($elem, $node);
    }

    // libxml2内部の文字列エンコーディングはUTF-8であるが、saveHTML()等の
    // メソッドでは読み込んだ文書のエンコーディングに再変換して出力される
    // (DOMDocumentのencodingプロパティを変更することで変られる)
    echo $doc->saveHTML();
}

// }}}
// {{{ showUnexpectedResponse()

/**
 * サーバから予期しないレスポンスが返ってきた旨を表示する
 *
 * @param   string $response    レスポンスボディ
 * @param   int $line   行番号
 * @return  void
 */
function showUnexpectedResponse($response, $line = null)
{
    echo '<html><head><title>p2 ERROR</title></head><body>';
    echo '<h1>p2 ERROR</h1><p>サーバからのレスポンスが変です。';
    if (is_numeric($line)) {
        echo "({$line})";
    }
    echo '</p><pre>';
    echo p2h($response);
    echo '</pre></body></html>';
}

// }}}
// {{{ getKeyInSubject()

/**
 *  subjectからkeyを取得する
 *
 * @return string|false
 */
function getKeyInSubject()
{
    global $host, $bbs, $ttitle;

    $aSubjectTxt = new SubjectTxt($host, $bbs);

    foreach ($aSubjectTxt->subject_lines as $l) {
        if (strpos($l, $ttitle) !== false) {
            if (preg_match("/^([0-9]+)\.(dat|cgi)(,|<>)(.+) ?(\(|（)([0-9]+)(\)|）)/", $l, $matches)) {
                return $key = $matches[1];
            }
        }
    }

    return false;
}

// }}}
// {{{ tab2space()

/**
 * 整形を維持しながら、タブをスペースに置き換える
 *
 * @param   string $in_str      対象文字列
 * @param   int $tabwidth       タブ幅
 * @param   string $linebreak   改行文字(列)
 * @return  string
 */
function tab2space($in_str, $tabwidth = 4, $linebreak = "\n")
{
    $out_str = '';
    $lines = preg_split('/\\r\\n|\\r|\\n/', $in_str);
    $ln = count($lines);
    $i = 0;

    while ($i < $ln) {
        $parts = explode("\t", rtrim($lines[$i]));
        $pn = count($parts);

        for ($j = 0; $j < $pn; $j++) {
            if ($j == 0) {
                $l = $parts[$j];
            } else {
                //$t = $tabwidth - (strlen($l) % $tabwidth);
                $sn = $tabwidth - (mb_strwidth($l) % $tabwidth); // UTF-8でも全角文字幅を2とカウントする
                for ($k = 0; $k < $sn; $k++) {
                    $l .= ' ';
                }
                $l .= $parts[$j];
            }
        }

        $out_str .= $l;
        if (++$i < $ln) {
            $out_str .= $linebreak;
        }
    }

    return $out_str;
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
