<?php
/**
 * rep2expack - フォント設定編集インタフェース
 */

// {{{ 初期化

// 初期設定読み込み & ユーザ認証
require_once __DIR__ . '/../init.php';
$_login->authorize();

require_once P2_LIB_DIR . '/fontconfig.inc.php';

$flexy_options = array(
    'templateDir' => './skin',
    'compileDir'  => $_conf['compile_dir'] . DIRECTORY_SEPARATOR . 'fontconfig',
    'locale' => 'ja',
    'charset' => 'Shift_JIS',
);

$fontconfig_types = array(
    'windows'   => 'Windows',
    'safari3'   => 'Safari3',
    'safari2'   => 'Safari2',
    'safari1'   => 'Safari1',
    'macosx'    => 'Mac OS X (Safari以外)',
    'macos9'    => 'Mac OS classic',
//  'pda'       => 'PDA, 携帯フルブラウザ', // 情報不足のため判定ルーチンが書けない
    'other'     => 'その他',
);
$fontconfig_params = array('fontfamily', 'fontfamily_bold', 'fontweight_bold', 'fontstyle_bold', 'fontfamily_aa', 'fontsize', 'menu_fontsize', 'sb_fontsize', 'read_fontsize', 'respop_fontsize', 'infowin_fontsize', 'form_fontsize');
$fontconfig_weights = array('normal', 'bold', 'lighter', 'bolder'/*, '100', '200', '300', '400', '500', '600', '700', '800', '900'*/);
$fontconfig_styles = array('normal', 'italic', 'oblique');
$fontconfig_sizes = array('6px', '8px', '9px', '10px', '11px', '12px', '13px', '14px', '16px', '18px', '21px', '24px');

$controllerObject = (object)array(
    'fontconfig_types' => $fontconfig_types,
    'fontconfig_params' => $fontconfig_params,
    'skindata' => p2_fontconfig_load_skin_setting(),
    'safari' => 0,
    'mac' => false,
);

if (file_exists($_conf['expack.skin.fontconfig_path'])) {
    $current_fontconfig = unserialize(file_get_contents($_conf['expack.skin.fontconfig_path']));
    if (!is_array($current_fontconfig)) {
        $current_fontconfig = array('enabled' => false, 'custom' => array());
    }
} else {
    FileCtl::make_datafile($_conf['expack.skin.fontconfig_path']);
    $current_fontconfig = array('enabled' => false, 'custom' => array());
}
$fontconfig_hash = md5(serialize($current_fontconfig));
$updated_fontconfig = array('enabled' => false, 'custom' => array());

// Mac はブラウザによって文字のレンダリング結果が大きく変わり
// その種類もそこそこ多いので現在のブラウザにマッチしないものを隠す
$ft = &$controllerObject->fontconfig_types;
$type = p2_fontconfig_detect_agent();
switch ($type) {
    case 'safari3':
        $controllerObject->safari = 3;
        unset($ft['safari2'], $ft['safari1'], $ft['macosx'], $ft['macos9']);
        break;
    case 'safari2':
        $controllerObject->safari = 2;
        unset($ft['safari3'], $ft['safari1'], $ft['macosx'], $ft['macos9']);
        break;
    case 'safari1':
        $controllerObject->safari = 1;
        unset($ft['safari3'], $ft['safari2'], $ft['macosx'], $ft['macos9']);
        break;
    case 'macosx':
        $controllerObject->mac = true;
        unset($ft['safari2'], $ft['safari1'], $ft['macos9']);
        break;
    case 'macos9':
        $controllerObject->mac = true;
        unset($ft['safari3'], $ft['safari2'], $ft['safari1'], $ft['macosx']);
        break;
    default:
        unset($ft['safari2'], $ft['safari1'], $ft['macosx'], $ft['macos9']);
}

// }}}

if (!is_dir($_conf['compile_dir'])) {
    FileCtl::mkdirRecursive($_conf['compile_dir']);
}

// テンプレートをコンパイル
$flexy = new HTML_Template_Flexy($flexy_options);
$flexy->compile('edit_user_font.tpl.html');
$elements = $flexy->getElements();

// カスタム設定を利用するか否かを切り替える
if (isset($_POST['use_skin'])) {
    $use_skin = is_array($_POST['use_skin']) ? current($_POST['use_skin']) : $_POST['use_skin'];
} else {
    $use_skin = !$current_fontconfig['enabled'];
}
if ($use_skin) {
    $elements['use_skin']->setAttributes(array('checked' => true));
    $elements['use_user']->setAttributes(array('checked' => false));
    $updated_fontconfig['enabled'] = false;
} else {
    $elements['use_skin']->setAttributes(array('checked' => false));
    $elements['use_user']->setAttributes(array('checked' => true));
    $updated_fontconfig['enabled'] = true;
}

// 変更の適用と、フォームへ値を代入
if (!empty($_POST['clear'])) {
    $_POST = array();
    $current_fontconfig['custom'] = array();
}
foreach ($fontconfig_params as $pname) {
    $elemName = $pname . '[%s]';
    if (isset($elements[$elemName])) {
        foreach ($fontconfig_types as $tname => $ttitle) {
            $newElemName = sprintf($elemName, $tname);
            if (!isset($elements[$newElemName])) {
                $elements[$newElemName] = clone $elements[$elemName];
            }
            if (!array_key_exists($tname, $updated_fontconfig['custom']) ||
                !is_array($updated_fontconfig['custom'][$tname]))
            {
                $updated_fontconfig['custom'][$tname] = array();
            }
            if (isset($_POST[$pname][$tname])) {
                $value = trim($_POST[$pname][$tname]);
            } elseif (isset($current_fontconfig['custom'][$tname][$pname])) {
                $value = $current_fontconfig['custom'][$tname][$pname];
            } else {
                $value = '';
            }
            if ($elements[$newElemName]->tag == 'select') {
                if (strpos($pname, 'fontweight') !== false) {
                    $option_values = $fontconfig_weights;
                    $option_labels = $fontconfig_weights;
                    array_unshift($option_values, '');
                    array_unshift($option_labels, 'inherit');
                    $elements[$newElemName]->setOptions(array_combine($option_values, $option_labels));
                    if ($value !== '' && !in_array($value, $fontconfig_weights)) {
                        $elements[$newElemName]->setOptions(array($value, $value));
                    }
                } elseif (strpos($pname, 'fontstyle') !== false) {
                    $option_values = $fontconfig_styles;
                    $option_labels = $fontconfig_styles;
                    array_unshift($option_values, '');
                    array_unshift($option_labels, 'inherit');
                    $elements[$newElemName]->setOptions(array_combine($option_values, $option_labels));
                    if ($value !== '' && !in_array($value, $fontconfig_styles)) {
                        $elements[$newElemName]->setOptions(array($value, $value));
                    }
                } elseif (strpos($pname, 'fontsize') !== false) {
                    $option_values = $fontconfig_sizes;
                    $option_labels = $fontconfig_sizes;
                    array_unshift($option_values, '');
                    array_unshift($option_labels, 'inherit');
                    $elements[$newElemName]->setOptions(array_combine($option_values, $option_labels));
                    if ($value !== '' && !in_array($value, $fontconfig_sizes)) {
                        $elements[$newElemName]->setOptions(array($value, $value));
                    }
                }
            }
            if ($value) {
                $updated_fontconfig['custom'][$tname][$pname] = $value;
            }
            $elements[$newElemName]->setValue($value);
        }
    }
}

// 保存
$fontconfig_data = serialize($updated_fontconfig);
$fontconfig_new_hath = md5($fontconfig_data);
if (strcmp($fontconfig_hash, $fontconfig_new_hath) != 0) {
    FileCtl::file_write_contents($_conf['expack.skin.fontconfig_path'], $fontconfig_data);
}

// スタイルシートをリセット
unset($STYLE);
include $skin;
foreach ($STYLE as $K => $V) {
    if (empty($V)) {
        $STYLE[$K] = '';
    } elseif (strpos($K, 'fontfamily') !== false) {
        $STYLE[$K] = p2_correct_css_fontfamily($V);
    } elseif (strpos($K, 'color') !== false) {
        $STYLE[$K] = p2_correct_css_color($V);
    } elseif (strpos($K, 'background') !== false) {
        $STYLE[$K] = 'url("' . addslashes($V) . '")';
    }
}
if ($updated_fontconfig['enabled']) {
    p2_fontconfig_apply_custom();
} else {
    $skin_en = preg_replace('/&amp;_=[^&]*/', '', $skin_en) . '&amp;_=' . rawurlencode($skin_uniq);
}
$controllerObject->STYLE = $STYLE;
$controllerObject->skin = $skin_en;
$controllerObject->p2vid = P2_VERSION_ID;

// 出力
$flexy->outputObject($controllerObject, $elements);

// {{{ p2_fontconfig_load_skin_setting()

/**
 * カスタム設定で上書きされていないスキン設定を読み込む
 */
function p2_fontconfig_load_skin_setting()
{
    global $_conf, $STYLE;

    $skindata = array();

    $fontfamily = (isset($STYLE['fontfamily.orig']))
        ? $STYLE['fontfamily.orig']
        : ((isset($STYLE['fontfamily'])) ? $STYLE['fontfamily'] : '');
    $skindata['fontfamily'] = p2_fontconfig_implode_fonts($fontfamily);

    $fontfamily_bold = (isset($STYLE['fontfamily_bold.orig']))
        ? $STYLE['fontfamily_bold.orig']
        : ((isset($STYLE['fontfamily_bold'])) ? $STYLE['fontfamily_bold'] : '');
    $skindata['fontfamily_bold'] = p2_fontconfig_implode_fonts($fontfamily_bold);

    $fontfamily_aa = (isset($_conf['expack.am.fontfamily.orig']))
        ? $_conf['expack.am.fontfamily.orig']
        : ((isset($_conf['expack.am.fontfamily'])) ? $_conf['expack.am.fontfamily'] : '');
    $skindata['fontfamily_aa'] = p2_fontconfig_implode_fonts($fontfamily_aa);

    $normal = ($skindata['fontfamily_bold'] == '') ? '' : 'normal';

    foreach (array('fontweight_bold', 'fontstyle_bold') as $key) {
        $skindata[$key] = (isset($STYLE[$key])) ? (string)$STYLE[$key] : $normal;
    }

    foreach (array('fontsize', 'menu_fontsize', 'sb_fontsize', 'read_fontsize',
                   'form_fontsize', 'respop_fontsize', 'infowin_fontsize') as $key)
    {
        $skindata[$key] = (isset($STYLE[$key])) ? (string)$STYLE[$key] : '';
    }

    return $skindata;
}

// }}}
// {{{ p2_fontconfig_implode_fonts()

function p2_fontconfig_implode_fonts($fonts)
{
    if (!is_array($fonts)) {
        $fonts = explode(',', (string)$fonts);
    }
    return '"' . implode('","', array_map('p2_fontconfig_trim', $fonts)) . '"';
}

// }}}
// {{{ p2_fontconfig_trim()

function p2_fontconfig_trim($str)
{
    return trim($str, " \r\n\t\x0B\"'" . P2_NULLBYTE);
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
