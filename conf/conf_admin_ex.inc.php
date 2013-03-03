<?php
/**
 * rep2expack - 拡張パック機能の On/Off とユーザ設定編集ページから変更させない設定
 *
 * このファイルの設定は、必要に応じて変更してください
 */

// {{{ 全般

// ImageCache2 等でファイルをリモートから取得する際の User-Agent
// 空の場合はブラウザのUser-Agentを送信する
$_conf['expack.user_agent'] = ""; // ("")

// コマンドライン版PHPのパス。できるだけ絶対パスで指定すること
$_conf['expack.php_cli_path'] = "php"; // ("php")

// Zend Framework (Zend Gdataでも可) のlibraryディレクトリへのパス
$_conf['expack.zf_path'] = ""; // ("")

// pecl_http が利用できる場合、HttpRequestPool による並列ダウンロードを有効にする
// (off:0, on:1, コマンドラインで実行:2)
$_conf['expack.use_pecl_http'] = 1; // (1)

// expack.use_pecl_http が 2 かつCLI用php.iniでhttpエクステンションを
// ロードするようになっていない場合のみ 1 にする
$_conf['expack.dl_pecl_http'] = 0; // (0)

// }}}
// {{{ スキン

// スキン（off:0, on:1）
$_conf['expack.skin.enabled'] = 1; // (1)

// 設定ファイルのパス
$_conf['expack.skin.setting_path'] = $_conf['pref_dir'] . '/p2_user_skin.txt';

// フォント設定ファイルのパス
$_conf['expack.skin.fontconfig_path'] = $_conf['pref_dir'] . '/p2_user_font.txt';

// }}}
// {{{ tGrep

// 一発検索リストのパス
$_conf['expack.tgrep.quick_file'] = $_conf['pref_dir'] . '/p2_tgrep_quick.txt';

// 検索履歴リストのパス
$_conf['expack.tgrep.recent_file'] = $_conf['pref_dir'] . '/p2_tgrep_recent.txt';

// }}}
// {{{ スマートポップアップメニュー

// SPM（off:0, on:1）
$_conf['expack.spm.enabled'] = 1; // (1)

// }}}
// {{{ アクティブモナー

// AA 補正（off:0, on:1）
$_conf['expack.am.enabled'] = 0; // (0)

// }}}
// {{{ 入力支援

// ActiveMona による AA プレビュー（off:0, on:1）
$_conf['expack.editor.with_activemona'] = 0; // (0)

// AAS による AA プレビュー（off:0, on:1）
$_conf['expack.editor.with_aas'] = 0; // (0)

// }}}
// {{{ RSSリーダ

// RSSリーダ（off:0, on:1）
$_conf['expack.rss.enabled'] = 0; // (0)

// 設定ファイルのパス
$_conf['expack.rss.setting_path'] = $_conf['pref_dir'] . '/p2_rss.txt';

// ImageCache2を使ってリンクされた画像をキャッシュする（off:0, on:1）
$_conf['expack.rss.with_imgcache'] = 0; // (0)

// }}}
// {{{ ImageCache2

/*
 * この機能を使うにはPHPのGD機能拡張またはImageMagickと
 * SQLite, PostgreSQL, MySQLのいずれかが必要。
 * 利用に当たっては doc/ImageCache2/README.txt と doc/ImageCache2/INSTALL.txt を
 * よく読んで、それに従ってください。
 */

// ImageCache2（off:0, PCのみ:1, 携帯のみ:2, 両方:3）
$_conf['expack.ic2.enabled'] = 0; // (0)

// 一時的なON/OFFの切替フラグを保存するファイルのパス
$_conf['expack.ic2.switch_path'] = $_conf['pref_dir'] . '/ic2_switch.txt';

// キャッシュしている画像をzipでまとめてダウンロード（off:0, on:1）
// PC版の画像キャッシュ一覧からのみ利用できる。
// zipエクステンションが必要。
$_conf['expack.ic2.zip'] = 0; // (0)

// }}}
// {{{ AAS

// AAS（off:0, on:1）
$_conf['expack.aas.enabled'] = 0; // (0)

//TrueTypeフォントのパス
$_conf['expack.aas.font_path'] = P2_BASE_DIR . '/ttf/mona.ttf';

// 数値参照のデコードに失敗したときの代替文字
$_conf['expack.aas.unknown_char'] = "?"; // ("?")

// フォント描画処理の文字コード
// "CP51932" では configure のオプションに --enable-gd-native-ttf が指定されていないと文字化けする
// このとき Unicode 対応フォントを使っているなら "UTF-8" にすると正しく表示できる
$_conf['expack.aas.output_charset'] = "CP51932"; // ("CP51932")

// }}}
// {{{ Dropbox

// アップロード先パス接頭辞
// 必ず "/" で始まること。また、最後の "/" 以降に文字があれば、
// それはアップロードされたファイル名の接頭辞になる。
$_conf['expack.dropbox.upload_prefix'] = "/uploads/"; // ("/uploads/")

// }}}
// {{{ その他

// お気にセット切り替え（off:0, on:1）
$_conf['expack.misc.multi_favs'] = 0; // (0)

// 利用するお気にセット数（お気にスレ・お気に板・RSSで共通）
$_conf['expack.misc.favset_num'] = 5; // (5)

// お気にセット名情報を記録するファイルのパス
$_conf['expack.misc.favset_file'] = $_conf['pref_dir'] . '/p2_favset.txt';

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
