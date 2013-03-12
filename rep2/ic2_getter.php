<?php
/**
 * ImageCache2 - ダウンローダ
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

// ポップアップウインドウ？
if (empty($_GET['popup'])) {
    $isPopUp = 0;
    $autoClose = -1;
} else {
    $isPopUp = 1;
    if (array_key_exists('close', $_GET) && is_numeric($_GET['close'])) {
        $autoClose = (float)$_GET['close'] * 1000.0;
        if ($autoClose > 0.0) {
            $autoClose = (int)$autoClose;
            if ($autoClose == 0) {
                $autoClose = 1;
            }
        } else {
            $autoClose = -1;
        }
    } else {
        $autoClose = -1;
    }
}

// }}}
// {{{ config


// 設定ファイル読み込み
$ini = ic2_loadconfig();

// フォームのデフォルト値
$qf_defaults = array(
    'uri'   => 'http://',
    'ref'   => '',
    'memo'  => '',
    'from'  => 'from',
    'to'    => 'to',
    'padding' => '',
    'popup'   => $isPopUp,
    'close'   => $autoClose,
);

// フォームの固定値
$qf_constants = array(
    '_hint'       => $_conf['detect_hint'],
    'download'    => 'ダウンロード',
    'reset'       => 'リセット',
    'close'       => '閉じる',
);

// プレビューの大きさ
$_preview_size = array(
    ImageCache2_Thumbnailer::SIZE_PC      => $ini['Thumb1']['width'] . '&times;' . $ini['Thumb1']['height'],
    ImageCache2_Thumbnailer::SIZE_MOBILE  => $ini['Thumb2']['width'] . '&times;' . $ini['Thumb2']['height'],
    ImageCache2_Thumbnailer::SIZE_INTERMD => $ini['Thumb3']['width'] . '&times;' . $ini['Thumb3']['height'],
);

// 属性
$_attr_uri    = array('size' => 50, 'onchange' => 'checkSerial(this.value)');
$_attr_s_chk  = array('onclick' => 'setSerialAvailable(this.checked)', 'id' => 's_chk');
$_attr_s_from = array('size' => 4, 'id' => 's_from');
$_attr_s_to   = array('size' => 4, 'id' => 's_to');
$_attr_s_pad  = array('size' => 1, 'id' => 's_pad');
$_attr_ref    = array('size' => 50);
$_attr_memo   = array('size' => 50);
$_attr_submit = array();
$_attr_reset  = array();
$_attr_close  = array('onclick' => 'window.close()');


// }}}
// {{{ prepare (Form & Template)


// 画像ダウンロード用フォームを設定
$_attribures = array('accept-charset' => 'UTF-8,Shift_JIS');
$_target = $isPopUp ? '_self' : 'read';

$qf = new HTML_QuickForm('get', 'get', $_SERVER['SCRIPT_NAME'], $_target, $_attribures);
$qf->setDefaults($qf_defaults);
$qf->setConstants($qf_constants);

// フォーム要素の定義
$qfe = array();

// 隠し要素
$qfe['detect_hint'] = $qf->addElement('hidden', '_hint');
$qfe['popup'] = $qf->addElement('hidden', 'popup');
$qfe['close'] = $qf->addElement('hidden', 'close');

// URLと連番設定
$qfe['uri']     = $qf->addElement('text', 'uri', 'URL', $_attr_uri);
$qfe['serial']  = $qf->addElement('checkbox', 'serial', '連番', null, $_attr_s_chk);
$qfe['from']    = $qf->addElement('text', 'from', 'From', $_attr_s_from);
$qfe['to']      = $qf->addElement('text', 'to', 'To', $_attr_s_to);
$qfe['padding'] = $qf->addElement('text', 'padding', '0で詰める桁数', $_attr_s_pad);

// リファラとメモ
$qfe['ref']  = $qf->addElement('text', 'ref', 'リファラ', $_attr_ref);
$qfe['memo'] = $qf->addElement('text', 'memo', '　　メモ', $_attr_memo);

// プレビューの大きさ
$preview_size = array();
foreach ($_preview_size as $value => $lavel) {
    $preview_size[$value] = HTML_QuickForm::createElement('radio', null, null, $lavel, $value);
}
$qf->addGroup($preview_size, 'preview_size', 'プレビュー', '&nbsp;');
if (!isset($_GET['preview_size'])) {
    $preview_size[1]->updateAttributes('checked="checked"');
}

// 決定・リセット・閉じる
$qfe['download'] = $qf->addElement('submit', 'download');
$qfe['reset']    = $qf->addElement('reset', 'reset');
$qfe['close']    = $qf->addElement('button', 'close', null, $_attr_close);

// Flexy
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
$flexy->setData('isPopUp', $isPopUp);
$flexy->setData('pc', !$_conf['ktai']);
$flexy->setData('iphone', $_conf['iphone']);
$flexy->setData('doctype', $_conf['doctype']);
$flexy->setData('extra_headers',   $_conf['extra_headers_ht']);
$flexy->setData('extra_headers_x', $_conf['extra_headers_xht']);

// }}}
// {{{ validate

$execDL = false;
if ($qf->validate() && ($params = $qf->getSubmitValues()) && isset($params['uri']) && isset($params['download'])) {
    $execDL = true;
    $params = array_map('trim', $params);

    // URLを検証
    $purl = @parse_url($params['uri']);
    if (!$purl || !preg_match('/^(https?)$/', $purl['scheme']) || empty($purl['host']) || empty($purl['path'])) {
        P2Util::pushInfoHtml('<p>エラー: 不正なURL</p>');
        $execDL = false;
        $isError = true;
    }

    // プレビューの大きさ
    if (isset($params['preview_size']) && in_array($params['preview_size'], array_keys($_preview_size))) {
        $thumb_type = (int)$params['preview_size'];
    } else {
        $thumb_type = 1;
    }

    // リファラとメモ
    $extra_params = '';
    if (isset($params['ref']) && strlen(trim($params['ref'])) > 0) {
        $extra_params .= '&ref=' . rawurlencode($params['ref']);
    }
    if (isset($params['memo']) && strlen(trim($params['memo'])) > 0) {
        $new_memo = ImageCache2_Normalizer::normalize($params['memo'], 'CP932');
        $_memo_en = rawurlencode($new_memo);
        // レンダリング時にhtmlspecialchars()されるので、ここでは&を&amp;にしない
        $extra_params .= '&memo=' . $_memo_en . '&' . $_conf['detect_hint_q_utf8'];
    } else {
        $new_memo = null;
    }


    // 連番
    $serial_pattern = '/\\[(\\d+)-(\\d+)\\]/';
    if (!empty($params['serial'])) {

        // プレースホルダとユーザ指定パラメータ
        if (strpos($params['uri'], '%s') !== false && !preg_match($serial_pattern, $params['uri'], $from_to)) {
            if (strpos(preg_replace('/%s/', ' ', $params['uri'], 1), '%s') !== false) {
                P2Util::pushInfoHtml('<p>エラー: URLに含められるプレースホルダは一つだけです。</p>');
                $execDL = false;
                $isError = true;
            } elseif (preg_match('/\\D/', $params['from']) || strlen($params['from']) == 0 ||
                      preg_match('/\\D/', $params['to'])   || strlen($params['to'])   == 0 ||
                      preg_match('/\\D/', $params['padding'])
            ) {
                P2Util::pushInfoHtml('<p>エラー: 連番パラメータに誤りがあります。</p>');
                $execDL = false;
                $isError = true;
            } else {
                $serial = array();
                $serial['from'] = (int)$params['from'];
                $serial['to']   = (int)$params['to'];
                if (strlen($params['padding']) == 0) {
                    $serial['pad'] = strlen($serial['to']);
                } else {
                    $serial['pad']  = (int)$params['padding'];
                }
             }

        // [from-to] を展開
        } elseif (preg_match($serial_pattern, $params['uri'], $from_to) && strpos($params['uri'], '%s') === false) {
            $params['uri'] = preg_replace($serial_pattern, '%s', $params['uri'], 1);
            if (preg_match($serial_pattern, $params['uri'])) {
                P2Util::pushInfoHtml('<p>エラー: URLに含められる連番パターンは一つだけです。</p>');
                $execDL = false;
                $isError = true;
            } else {
                $serial = array();
                $serial['from'] = (int)$from_to[1];
                $serial['to']   = (int)$from_to[2];
                if (strlen($from_to[1]) == strlen($from_to[2])) {
                    $serial['pad'] = strlen($from_to[2]);
                /*} elseif (strlen($from_to[1]) < strlen($from_to[2]) && strlen($from_to[1]) > 1 && substr($from_to[1]) == '0') {
                    $serial['pad'] = strlen($from_to[1]);*/
                } else {
                    $serial['pad'] = 0;
                }
            }

        // どちらも無いか、両方がある
        } else {
            P2Util::pushInfoHtml('<p>エラー: URLに連番のプレースホルダ(<samp>%s</samp>)またはパターン(<samp>[from-to]</samp>)が含まれていないか、両方が含まれています。</p>');
            $execDL = false;
            $isError = true;
        }

        // 範囲を検証
        if (isset($serial) && $serial['from'] >= $serial['to']) {
            P2Util::pushInfoHtml('<p>エラー: 連番の終りの番号は始まりの番号より大きくないといけません。</p>');
            $execDL = false;
            $isError = true;
            $serial = null;
        }

    // 連番なし
    } else {
        if (strpos($params['uri'], '%s') !== false || preg_match($serial_pattern, $params['uri'], $from_to)) {
            P2Util::pushInfoHtml('<p>エラー: 連番にチェックが入っていませんが、URLに連番ダウンロード用の文字列が含まれています。</p>');
            $execDL = false;
            $isError = true;
        }
        $qfe['from']->updateAttributes('disabled="disabled"');
        $qfe['to']->updateAttributes('disabled="disabled"');
        $qfe['padding']->updateAttributes('disabled="disabled"');
        $serial = null;
    }

} else {
    $qfe['from']->updateAttributes('disabled="disabled"');
    $qfe['to']->updateAttributes('disabled="disabled"');
    $qfe['padding']->updateAttributes('disabled="disabled"');
}


// }}}
// {{{ generate


if ($execDL) {

    if (is_null($serial)) {
        $URLs = array($params['uri']);
    } else {
        $URLs = array();
        for ($i = $serial['from']; $i <= $serial['to']; $i++) {
            // URLエンコードされた文字列も%を含むので sprintf() は使わない。
            // URLエンコードのフォーマットは%+16進数なので"%s"を置換しても影響しない。
            $URLs[] = str_replace('%s', str_pad($i, $serial['pad'], '0', STR_PAD_LEFT), $params['uri']);
        }
    }

    $thumbnailer = new ImageCache2_Thumbnailer($thumb_type);
    $images = array();

    foreach ($URLs as $url) {
        $icdb = new ImageCache2_DataObject_Images();
        $img_title = p2h($url);
        $url_en = rawurlencode($url);
        $src_url = 'ic2.php?r=1&uri=' . $url_en;
        $thumb_url = 'ic2.php?r=1&t=' . $thumb_type . '&uri=' . $url_en;
        $thumb_x = '';
        $thumb_y = '';
        $img_memo = $new_memo;

         // 画像がブラックリストorエラーログにあるとき
        if (false !== ($errcode = $icdb->ic2_isError($url))) {
            $img_title = "<s>{$img_title}</s>";
            $thumb_url = "./img/{$errcode}.png";

        // 既にキャッシュされているとき
        } elseif ($icdb->get($url)) {
            $_src_path = $thumbnailer->srcPath($icdb->size, $icdb->md5, $icdb->mime);
            if (file_exists($_src_path)) {
                $src_url = $thumbnailer->srcUrl($icdb->size, $icdb->md5, $icdb->mime);
            }
            $_thumb_path = $thumbnailer->thumbPath($icdb->size, $icdb->md5, $icdb->mime);
            if (file_exists($_thumb_path)) {
                $thumb_url = $thumbnailer->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);
            }
            if (preg_match('/(\d+)x(\d+)/', $thumbnailer->calc($icdb->width, $icdb->height), $thumb_xy)) {
                $thumb_x = $thumb_xy[1];
                $thumb_y = $thumb_xy[2];
            }
            // メモが記録されていないときはDBを更新
            if (isset($new_memo) && strpos($icdb->memo, $new_memo) === false){
                $update = clone $icdb;
                if (!is_null($icdb->memo) && strlen($icdb->memo) > 0) {
                    $update->memo = $new_memo . ' ' . $icdb->memo;
                } else {
                    $update->memo = $new_memo;
                }
                $update->update();
                $img_memo = $update->memo;
            } elseif (!is_null($icdb->memo) && strlen($icdb->memo) > 0) {
                $img_memo = $icdb->memo;
            }

        // キャッシュされていないとき
        } else {
            $src_url .= $extra_params;
            $thumb_url .= $extra_params;
        }

        $img = new stdClass();
        $img->title     = $img_title;
        $img->src_url   = $src_url;
        $img->thumb_url = $thumb_url;
        $img->thumb_x   = $thumb_x;
        $img->thumb_y   = $thumb_y;
        $img->memo      = mb_convert_encoding($img_memo, 'CP932', 'UTF-8');
        $img->url       = $url;
        $images[] = $img;
    }

    $flexy->setData('images', $images);
    if ($isPopUp) {
        $flexy->setData('showForm', true);
    }
} else {
    if (empty($isError) || $isPopUp) {
        $flexy->setData('showForm', true);
    }
}

// }}}
// {{{ output


// フォームをテンプレート用オブジェクトに変換
$r = new HTML_QuickForm_Renderer_ObjectFlexy($flexy);
//$r->setLabelTemplate('_label.tpl.html');
//$r->setHtmlTemplate('_html.tpl.html');
$qf->accept($r);
$qfObj = $r->toObject();

// 動的JavaScript
$js = $qf->getValidationScript();
$js .= <<<EOS
<script type="text/javascript">
// <![CDATA[
function ic2g_onload()
{
\tsetWinTitle();\n
EOS;
if ($execDL && $autoClose > 0) {
    $js .= "\twindow.setTimeout('window.close();', $autoClose);\n";
}
$js .= <<<EOS
}
// ]]>
</script>
EOS;

// 変数をAssign
$flexy->setData('info_msg', P2Util::getInfoHtml());
$flexy->setData('STYLE', $STYLE);
$flexy->setData('js', $js);
$flexy->setData('get', $qfObj);

// ページを表示
P2Util::header_nocache();
$flexy->compile('ic2g.tpl.html');
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
