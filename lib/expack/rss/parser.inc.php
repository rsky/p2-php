<?php
/**
 * rep2expack - RSS Parser
 */
use ImageCache2\Entity\Image;

require_once P2EX_LIB_DIR . '/rss/common.inc.php';
require_once 'XML/RSS.php';

// {{{ ImageCache2との連携判定

if ($GLOBALS['_conf']['expack.rss.with_imgcache'] &&
    ((!$GLOBALS['_conf']['ktai'] && $GLOBALS['_conf']['expack.ic2.enabled'] % 2 == 1) ||
    ($GLOBALS['_conf']['ktai'] && $GLOBALS['_conf']['expack.ic2.enabled'] >= 2)))
{
    if (!class_exists('ImageCache2_Switch', false)) {
        require P2EX_LIB_DIR . '/ImageCache2/Switch.php';
    }
    if (ImageCache2_Switch::get($GLOBALS['_conf']['ktai'])) {
        if (!function_exists('rss_get_image')) {
            require P2EX_LIB_DIR . '/rss/getimage.inc.php';
        }
        define('P2_RSS_IMAGECACHE_AVAILABLE', 1);
    } else {
        define('P2_RSS_IMAGECACHE_AVAILABLE', 0);
    }
} else {
    define('P2_RSS_IMAGECACHE_AVAILABLE', 0);
}

// }}}
// {{{ p2GetRSS()

/**
 * RSSをダウンロードし、パース結果を返す
 */
function p2GetRSS($remotefile, $atom = 0)
{
    global $_conf;

    $refresh = (!empty($_GET['refresh']) || !empty($_POST['refresh']));

    $localpath = rss_get_save_path($remotefile);
    if (PEAR::isError($localpath)) {
        P2Util::pushInfoHtml('<p>' . $localpath->getMessage() . '</p>');
        return $localpath;
    }

    // 保存用ディレクトリがなければつくる
    if (!is_dir(dirname($localpath))) {
        FileCtl::mkdirFor($localpath);
    }

    // If-Modified-Sinceつきでダウンロード（ファイルが無いか、古いか、強制リロードのとき）
    if (!file_exists($localpath) || $refresh ||
        filemtime($localpath) < (time() - $_conf['expack.rss.check_interval'] * 60)
    ) {
        $dl = P2Util::fileDownload($remotefile, $localpath, true, 301);
    }

    // キャッシュが更新されなかったか、ダウンロード成功ならRSSをパース
    if (file_exists($localpath) && (!isset($dl) || $dl->isSuccess())) {
        if ($atom) {
            $atom = (isset($dl) && $dl->code == 200) ? 2 : 1;
        }
        $rss = p2ParseRSS($localpath, $atom);
        return $rss;
    } else {
        return $dl;
    }

}

// }}}
// {{{ p2ParseRSS()

/**
 * RSSをパースする
 */
function p2ParseRSS($xmlpath, $atom=0)
{
    // $atomが真ならXSLを使ってRSS 1.0に変換
    // （変換済みファイルが存在しないか、$atom==2のときに実行される）
    // 元のXML(Atom)でencoding属性が正しく指定されていればXSLTプロセッサが自動で
    // 文字コードをUTF-8(XSLで指定した文字コード)に変換してくれる
    if ($atom) {
        $xslpath = P2EX_LIB_DIR . '/rss/atom03-to-rss10.xsl';
        $rsspath = $xmlpath . '.rss';
        if (file_exists($rsspath) && $atom != 2) {
            // OK
        } elseif (extension_loaded('xsl')) {
            if (!atom_to_rss($xmlpath, $xslpath, $rsspath)) {
                $retval = false;
                return $retval;
            }
        } else {
            P2Util::pushInfoHtml('<p>p2 error: Atomフィードを読むにはPHPのXSL機能拡張が必要です。</p>');
            $retval = false;
            return $retval;
        }
    } else {
        $rsspath = $xmlpath;
    }

    // エンコーディングを判定し、XML_RSSクラスのインスタンスを生成する
    // 2006-02-01 手動判定廃止
    /*$srcenc = 'UTF-8';
    $tgtenc = 'UTF-8';
    if ($fp = @fopen($rsspath, 'rb')) {
        $content = fgets($fp, 64);
        if (preg_match('/<\\?xml version=(["\'])1.0\\1 encoding=(["\'])(.+?)\\2 ?\\?>/', $content, $matches)) {
            $srcenc = $matches[3];
        }
        fclose($fp);
    }
    $rss = new XML_RSS($rsspath, $srcenc, $tgtenc);*/
    $rss = new XML_RSS($rsspath);
    if (PEAR::isError($rss)) {
        P2Util::pushInfoHtml('<p>p2 error: RSS - ' . $rss->getMessage() . '</p>');
        return $rss;
    }
    // 解析対象のタグを上書き
    $rss->channelTags = array_unique(array_merge($rss->channelTags, array (
        'CATEGORY', 'CLOUD', 'COPYRIGHT', 'DESCRIPTION', 'DOCS', 'GENERATOR', 'IMAGE',
        'ITEMS', 'LANGUAGE', 'LASTBUILDDATE', 'LINK', 'MANAGINGEditor', 'PUBDATE',
        'RATING', 'SKIPDAYS', 'SKIPHOURS', 'TEXTINPUT', 'TITLE', 'TTL', 'WEBMASTER'
    )));
    $rss->itemTags = array_unique(array_merge($rss->itemTags, array (
        'AUTHOR', 'CATEGORY', 'COMMENTS', 'CONTENT:ENCODED', 'DESCRIPTION',
        'ENCLOSURE', 'GUID', 'LINK', 'PUBDATE', 'SOURCE', 'TITLE'
    )));
    $rss->imageTags = array_unique(array_merge($rss->imageTags, array (
        'DESCRIPTION', 'HEIGHT', 'LINK', 'TITLE', 'URL', 'WIDTH'
    )));
    $rss->textinputTags = array_unique(array_merge($rss->textinputTags, array (
        'DESCRIPTION', 'LINK', 'NAME', 'TITLE'
    )));
    $rss->moduleTags = array_unique(array_merge($rss->moduleTags, array (
        'BLOGCHANNEL:BLOGROLL', 'BLOGCHANNEL:CHANGES', 'BLOGCHANNEL:MYSUBSCRIPTIONS',
        'CC:LICENSE', 'CONTENT:ENCODED', 'DC:CONTRIBUTOR', 'DC:COVERAGE',
        'DC:CREATOR', 'DC:DATE', 'DC:DESCRIPTION', 'DC:FORMAT', 'DC:IDENTIFIER',
        'DC:LANGUAGE', 'DC:PUBDATE', 'DC:PUBLISHER', 'DC:RELATION', 'DC:RIGHTS',
        'DC:SOURCE', 'DC:SUBJECT', 'DC:TITLE', 'DC:TYPE',
        'SY:UPDATEBASE', 'SY:UPDATEFREQUENCY', 'SY:UPDATEPERIOD'
    )));
    // RSSをパース
    $result = $rss->parse();
    if (PEAR::isError($result)) {
        P2Util::pushInfoHtml('<p>p2 error: RSS - ' . $result->getMessage() . '</p>');
        return $result;
    }

    return $rss;
}

// }}}
// {{{ atom_to_rss()

/**
 * Atom 0.3 を RSS 1.0 に変換する（共通）
 */
function atom_to_rss($input, $stylesheet, $output)
{
    global $_conf;

    // 保存用ディレクトリがなければつくる
    if (!is_dir(dirname($output))) {
        FileCtl::mkdirFor($output);
    }

    // 変換
    if (extension_loaded('xslt')) { // PHP4, Sablotron
        $rss_content = atom_to_rss_by_xslt($input, $stylesheet, $output);
    } elseif (extension_loaded('xsl')) { // PHP5, LibXSLT
        $rss_content = atom_to_rss_by_xsl($input, $stylesheet, $output);
    }

    // チェック
    if (!$rss_content) {
        if (file_exists($output)) {
            unlink($output);
        }
        return false;
    }

    // FreeBSD 5.3 Ports の textproc/php4-xslt ではバグのせいか変換の際に名前空間が失われるので補正する
    // (php4-xslt-4.3.10_2, expat-1.95.8, libiconv-1.9.2_1, Sablot-1.0.1)
    // バグのない環境なら何も変わらない・・・はず。
    $rss_fix_patterns = array(
        '/<(\/)?(RDF|Seq|li)( .+?)?>/u'       => '<$1rdf:$2$3>',
        '/<(channel|item) about=/u'           => '<$1 rdf:about=',
        '/<(\/)?(encoded)>/u'                 => '<$1content:$2>',
        '/<(\/)?(creator|subject|date|pubdate)>/u' => '<$1dc:$2>');
    $rss_fixed = preg_replace(array_keys($rss_fix_patterns), array_values($rss_fix_patterns), $rss_content);
    if (md5($rss_content) != md5($rss_fixed)) {
        $fp = @fopen($output, 'wb') or p2die("cannot write. ({$output})");
        flock($fp, LOCK_EX);
        fwrite($fp, $rss_fixed);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    return true;
}

// }}}
// {{{ atom_to_rss_by_xslt()

/**
 * Atom 0.3 を RSS 1.0 に変換する（PHP4, XSLT）
 */
function atom_to_rss_by_xslt($input, $stylesheet, $output)
{
    $xh = xslt_create();
    if (!@xslt_process($xh, $input, $stylesheet, $output)) {
        $errmsg = xslt_errno($xh) . ': ' . xslt_error($xh);
        P2Util::pushInfoHtml('<p>p2 error: XSLT - AtomをRSSに変換できませんでした。(' . $errmsg . ')</p>');
        xslt_free($xh);
        return FALSE;
    }
    xslt_free($xh);

    return FileCtl::file_read_contents($output);
}

// }}}
// {{{ atom_to_rss_by_xsl()

/**
 * Atom 0.3 を RSS 1.0 に変換する（PHP5, DOM & XSL）
 */
function atom_to_rss_by_xsl($input, $stylesheet, $output)
{
    $xmlDoc = new DomDocument();
    if ($xmlDoc->load(realpath($input))) {
        $xslDoc = new DomDocument();
        $xslDoc->load(realpath($stylesheet));

        $proc = new XSLTProcessor();
        $proc->importStyleSheet($xslDoc);

        $rssDoc = $proc->transformToDoc($xmlDoc);
        $rssDoc->save($output);

        $rss_content = FileCtl::file_read_contents($output);
    } else {
        $rss_content = null;
    }

    if (!$rss_content) {
        P2Util::pushInfoHtml('<p>p2 error: XSL - AtomをRSSに変換できませんでした。</p>');
        return FALSE;
    }

    return $rss_content;
}

// }}}
// {{{ rss_item_exists()

/**
 * RSSのitem要素に任意の子要素があるかどうかをチェックする
 * 空要素は無視
 */
function rss_item_exists($items, $element)
{
    foreach ($items as $item) {
        if (isset($item[$element]) && strlen(trim($item[$element])) > 0) {
            return TRUE;
        }
    }
    return FALSE;
}

// }}}
// {{{ rss_format_date()

/**
 * RSSの日付を表示用に調整する
 */
function rss_format_date($date)
{
    if (preg_match('/(?P<date>(\d\d)?\d\d-\d\d-\d\d)T(?P<time>\d\d:\d\d(:\d\d)?)(?P<zone>([+\-])(\d\d):(\d\d)|Z)?/', $date, $t)) {
        $time = $t['date'].' '.$t['time'].' ';
        if ($t['zone'] && $t['zone'] != 'Z') {
            $time .= $t[6].$t[7].$t[8]; // [+-]HHMM
        } else {
            $time .= 'GMT';
        }
        return date('y/m/d H:i:s', strtotime($time));
    }
    return p2h($date);
}

// }}}
// {{{ rss_desc_converter()

/**
 * RSSのdescription要素を表示用に調整する
 */
function rss_desc_converter($description)
{
    // HTMLタグがなければCR+LF/CR/LFを<br>+LFにするなど、軽く整形する
    if (!preg_match('/<(\/?[A-Za-z]+[1-6]?)( [^>]+>)?( ?\/)?>/', $description)) {
        return preg_replace('/[ \t]*(\r\n?|\n)[ \t]*/', "<br>\n", trim($description));
    }

    // 許可するタグ一覧
    $allowed_tags = '<a><b><i><u><s><strong><em><code><br><h1><h2><h3><h4><h5><h6><p><div><address><blockquote><ol><ul><li><img>';

    // script要素とstyle要素は中身ごとまとめて消去
    $description = preg_replace('/<(script|style)(?: .+?)?>(.+?)?<\/\1>/is', '', $description);
    // 不許可のタグを消去
    $description = strip_tags($description, $allowed_tags);
    // タグの属性チェック
    $description = preg_replace_callback('/<(\/?[A-Za-z]+[1-6]?)( [^>]+?)?>/', 'rss_desc_tag_cleaner', $description);

    return $description;
}

// }}}
// {{{ rss_desc_tag_cleaner()

/**
 * 無効タグ属性などを消去するコールバック関数
 */
function rss_desc_tag_cleaner($tag)
{
    global $_conf;

    $element = strtolower($tag[1]);
    $attributes = trim($tag[2]);
    $close = trim($tag[3]); // HTML 4.01形式で表示するので無視

    // 終了タグなら
    if (!$attributes || substr($element, 0, 1) == '/') {
        return '<'.$element.'>';
    }

    $tag = '<'.$element;
    if (preg_match_all('/(?:^| )([A-Za-z\-]+)\s*=\s*("[^"]*"|\'[^\']*\'|\w[^ ]*)(?: |$)/', $attributes, $matches, PREG_SET_ORDER)) {

        foreach ($matches as $attr) {
            $key = strtolower($attr[1]);
            $value = $attr[2];

            // JavaScriptイベントハンドラ・スタイルシート・ターゲットなどの属性は禁止
            if (preg_match('/^(on[a-z]+|style|class|id|target)$/', $key)) {
                continue;
            }

            // 値の引用符を削除
            $q = substr($value, 0, 1);
            if ($q == "'") {
                $value = str_replace('"', '&quot;', substr($value, 1, -1));
            } elseif ($q == '"') {
                $value = substr($value, 1, -1);
            }

            // 属性で分岐
            switch ($key) {
                case 'href':
                    if ($element != 'a' || preg_match('/^javascript:/i', $value)) {
                        break; // a要素以外はhref属性禁止
                    }
                    if (preg_match('|^[^/:]*/|', $value)) {
                        $value = rss_url_rel_to_abs($value);
                    }
                    return '<a href="'.P2Util::throughIme($value).'"'.$_conf['ext_win_target_at'].'>';
                case 'src':
                    if ($element != 'img' || preg_match('/^javascript:/i', $value)) {
                        break; // img要素以外はsrc属性禁止
                    }
                    if (preg_match('|^[^/:]*/|', $value)) {
                        $value = rss_url_rel_to_abs($value);
                    }
                    if (P2_RSS_IMAGECACHE_AVAILABLE) {
                        $image = rss_get_image($value, $GLOBALS['channel']['title']);
                        if ($image[3] != Image::OK) {
                            if ($_conf['ktai']) {
                                // あぼーん画像 - 携帯
                                switch ($image[3]) {
                                    case Image::ABORN:  return '[p2:あぼーん画像]';
                                    case Image::BROKEN: return '[p2:壊]'; // これと
                                    case Image::LARGE:  return '[p2:大]'; // これは現状では無効
                                    case Image::VIRUS:  return '[p2:ウィルス警告]';
                                    default : return '[p2:unknown error]'; // 予備
                                }
                            } else {
                                // あぼーん画像 - PC
                                return "<img src=\"{$image[0][0]}\" {$image[0][1]}>";
                            }
                        } elseif ($_conf['ktai']) {
                            // インライン表示 - 携帯（PC用サムネイルサイズ）
                            return "<img src=\"{$image[1][0]}\" {$image[1][1]}>";
                        } else {
                            // インライン表示 - PC（フルサイズ）
                            return "<img src=\"{$image[0][0]}\" {$image[0][1]}>";
                        }
                    }
                    // イメージキャッシュが無効のとき画像は表示しない
                    return '';
                case 'alt':
                    if ($element == 'img' && !P2_RSS_IMAGECACHE_AVAILABLE) {
                        return ' [img:'.$value.']'; // 画像はalt属性を代わりに表示
                    }
                    $tag .= ' ="'.$value.'"';
                    break;
                case 'width':
                case 'height':
                    // とりあえず無視
                    break;
                default:
                    $tag .= ' ="'.$value.'"';
            }

        } // endforeach

        // 要素で最終確認
        switch ($element) {
            // href属性がなかったa要素
            case 'a':
                return '<a>';
            // alt属性がなかったimg要素
            case 'img':
                return '';
        }
    } // endif
    $tag .= '>';

    return $tag;
}

// }}}
// {{{ rss_url_rel_to_abs()

/**
 * 相対 URL を絶対 URL にして返す関数
 *
 * グローバル変数を参照するより引数として RSS の URL を与えられる方が望ましいが
 * 変更が必要な箇所が多かったので手抜き
 */
function rss_url_rel_to_abs($url)
{
    // URL をパース
    $p = @parse_url($GLOBALS['channel']['link']);
    if (!$p || !isset($p['scheme']) || $p['scheme'] != 'http' || !isset($p['host'])) {
        return $url;
    }

    // ルート URL を作成
    $top = $p['scheme'] . '://';
    if (isset($p['user'])) {
        $top .= $p['user'];
        if (isset($p['pass'])) {
            $top .= '@' . $p['pass'];
        }
        $top .= ':';
    }
    $top .= $p['host'];
    if (isset($p['port'])) {
        $top .= ':' . $p['port'];
    }

    // 絶対パスならルート URL と結合して返す
    if (substr($url, 0, 1) == '/') {
        return $top . $url;
    }

    // ルート URL にスラッシュを付加
    $top .= '/';

    // チャンネルのパスを分解
    if (isset($p['path'])) {
        $paths1 = explode('/', trim($p['path'], '/'));
    } else {
        $paths1 = array();
    }

    // 相対 URL を分解
    if ($query = strstr($url, '?')) {
        $paths2 = explode('/', substr($url, 0, strlen($query) * -1));
    } else {
        $paths2 = explode('/', $url);
        $query = '';
    }

    // 分解した相対 URL のパスを絶対パスに加える
    while (($s = array_shift($paths2)) !== null) {
        $r = $s;
        switch ($s) {
            case '':
            case '.':
                // pass
                break;
            case '..':
                array_pop($paths1);
                break;
            default:
                array_push($paths1, $s);
        }
    }
    // 相対パスがスラッシュで終わっていたときの処理
    if ($r === '') {
        array_push($paths1, '');
    }

    //絶対 URL を返す
    return $top . implode('/', $paths1) . $query;
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
