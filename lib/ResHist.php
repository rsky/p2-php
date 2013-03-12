<?php
/**
 * rep2 - 書き込み履歴のクラス
 */

// {{{ ResHist

/**
 * 書き込みログのクラス
 */
class ResHist
{
    // {{{ properties

    public $articles; // クラス ResArticle のオブジェクトを格納する配列
    public $num; // 格納された BrdMenuCate オブジェクトの数

    public $resrange; // array( 'start' => i, 'to' => i, 'nofirst' => bool )

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->articles = array();
        $this->num = 0;
    }

    // }}}
    // {{{ readLines()

    /**
     * 書き込みログの lines をパースして読み込む
     *
     * @param  array    $lines
     * @return void
     */
    public function readLines(array $lines)
    {
        $n = 1;

        foreach ($lines as $aline) {
            $aResArticle = new ResArticle();

            $resar = explode('<>', $aline);
            $aResArticle->name      = $resar[0];
            $aResArticle->mail      = $resar[1];
            $aResArticle->daytime   = $resar[2];
            $aResArticle->msg       = $resar[3];
            $aResArticle->ttitle    = $resar[4];
            $aResArticle->host      = $resar[5];
            $aResArticle->bbs       = $resar[6];
            if (!($aResArticle->itaj = P2Util::getItaName($aResArticle->host, $aResArticle->bbs))) {
                $aResArticle->itaj  = $aResArticle->bbs;
            }
            $aResArticle->key       = $resar[7];
            $aResArticle->resnum    = $resar[8];

            $aResArticle->order = $n;

            $this->addRes($aResArticle);

            $n++;
        }
    }

    // }}}
    // {{{ addRes()

    /**
     * レスを追加する
     *
     * @return void
     */
    public function addRes(ResArticle $aResArticle)
    {
        $this->articles[] = $aResArticle;
        $this->num++;
    }

    // }}}
    // {{{ showArticles()

    /**
     * レス記事を表示する PC用
     *
     * @return void
     */
    public function showArticles()
    {
        global $_conf, $STYLE;

        // Pager 準備
        $perPage = 100;
        $params = array(
            'mode'       => 'Jumping',
            'itemData'   => $this->articles,
            'perPage'    => $perPage,
            'delta'      => 10,
            'clearIfVoid' => true,
            'prevImg' => "前の{$perPage}件",
            'nextImg' => "次の{$perPage}件",
            //'separator' => '|',
            //'expanded' => true,
            'spacesBeforeSeparator' => 2,
            'spacesAfterSeparator' => 0,
        );

        $pager = Pager::factory($params);
        $links = $pager->getLinks();
        $data  = $pager->getPageData();

        if ($pager->links) {
            echo "<div>{$pager->links}</div>";
        }

        echo "<div class=\"thread\">\n";

        foreach ($data as $a_res) {
            $hd['daytime'] = p2h($a_res->daytime);
            $hd['ttitle'] = p2h(html_entity_decode($a_res->ttitle, ENT_COMPAT, 'Shift_JIS'));
            $hd['itaj'] = p2h($a_res->itaj);

            $href_ht = "";
            if ($a_res->key) {
                if (empty($a_res->resnum) || $a_res->resnum == 1) {
                    $ls_q = '';
                    $footer_q = '#footer';
                } else {
                    $lf = max(1, $a_res->resnum - 0);
                    $ls_q = "&amp;ls={$lf}-";
                    $footer_q = "#r{$lf}";
                }
                $time = time();
                $href_ht = "{$_conf['read_php']}?host={$a_res->host}&amp;bbs={$a_res->bbs}&amp;key={$a_res->key}{$ls_q}{$_conf['k_at_a']}&amp;nt={$time}{$footer_q}";
            }
            $info_view_ht = <<<EOP
        <a href="info.php?host={$a_res->host}&amp;bbs={$a_res->bbs}&amp;key={$a_res->key}{$_conf['k_at_a']}" target="_self" onclick="return OpenSubWin('info.php?host={$a_res->host}&amp;bbs={$a_res->bbs}&amp;key={$a_res->key}&amp;popup=1',{$STYLE['info_pop_size']},0,0)">情報</a>
EOP;

            $res_ht = "<div class=\"res\">\n";
            $res_ht .= "<div class=\"res-header\"><input name=\"checked_hists[]\" type=\"checkbox\" value=\"{$a_res->order},,,,{$hd['daytime']}\"> ";
            $res_ht .= "{$a_res->order} ："; // 番号
            $res_ht .= '<span class="name"><b>' . p2h($a_res->name) . '</b></span> ：'; // 名前
            // メール
            if ($a_res->mail) {
                $res_ht .= p2h($a_res->mail) . ' ：';
            }
            $res_ht .= "{$hd['daytime']}</div>\n"; // 日付とID
            // 板名
            $res_ht .= "<div class=\"res-hist-board\"><a href=\"{$_conf['subject_php']}?host={$a_res->host}&amp;bbs={$a_res->bbs}{$_conf['k_at_a']}\" target=\"subject\">{$hd['itaj']}</a> / ";
            if ($href_ht) {
                $res_ht .= "<a href=\"{$href_ht}\"><b>{$hd['ttitle']}</b></a> - {$info_view_ht}";
            } elseif ($hd['ttitle']) {
                $res_ht .= "<b>{$hd['ttitle']}</b>";
            }
            $res_ht .= "</div>\n";
            $res_ht .= "<div class=\"message\">{$a_res->msg}</div>\n"; // 内容
            $res_ht .= "</div>\n";

            echo $res_ht;
            flush();
        }

        echo "</div>\n";

        if ($pager->links) {
            echo "<div>{$pager->links}</div>";
        }
    }

    // }}}
    // {{{ showNaviK()

    /**
     * 携帯用ナビを表示する
     * 表示範囲もセットされる
     */
    public function showNaviK($position)
    {
        global $_conf;

        // 表示数制限
        $list_disp_all_num = $this->num;
        $list_disp_range = $_conf['mobile.rnum_range'];

        if ($_GET['from']) {
            $list_disp_from = $_GET['from'];
            if ($_GET['end']) {
                $list_disp_range = $_GET['end'] - $list_disp_from + 1;
                if ($list_disp_range < 1) {
                    $list_disp_range = 1;
                }
            }
        } else {
            $list_disp_from = 1;
            /*
            $list_disp_from = $this->num - $list_disp_range + 1;
            if ($list_disp_from < 1) {
                $list_disp_from = 1;
            }
            */
        }
        $disp_navi = P2Util::getListNaviRange($list_disp_from, $list_disp_range, $list_disp_all_num);

        $this->resrange['start'] = $disp_navi['from'];
        $this->resrange['to'] = $disp_navi['end'];
        $this->resrange['nofirst'] = false;

        if ($disp_navi['from'] > 1) {
            if ($position == 'footer') {
                $mae_ht = <<<EOP
<a href="read_res_hist.php?from={$disp_navi['mae_from']}{$_conf['k_at_a']}"{$_conf['k_accesskey_at']['prev']}>{$_conf['k_accesskey_st']['prev']}前</a>
EOP;
            } else {
                $mae_ht = <<<EOP
<a href="read_res_hist.php?from={$disp_navi['mae_from']}{$_conf['k_at_a']}">前</a>
EOP;
            }
        }
        if ($disp_navi['end'] < $list_disp_all_num) {
            if ($position == 'footer') {
                $tugi_ht = <<<EOP
<a href="read_res_hist.php?from={$disp_navi['tugi_from']}{$_conf['k_at_a']}"{$_conf['k_accesskey_at']['next']}>{$_conf['k_accesskey_st']['next']}次</a>
EOP;
            } else {
                $tugi_ht = <<<EOP
<a href="read_res_hist.php?from={$disp_navi['tugi_from']}{$_conf['k_at_a']}">次</a>
EOP;
            }
        }

        if (!$disp_navi['all_once']) {
            echo "<div class=\"navi\">{$disp_navi['range_st']} {$mae_ht} {$tugi_ht}</div>\n";
        }
    }

    // }}}
    // {{{ showArticlesK()

    /**
     * レス記事を表示するメソッド 携帯用
     *
     * @return void
     */
    public function showArticlesK()
    {
        global $_conf;

        foreach ($this->articles as $a_res) {
            $hd['daytime'] = p2h($a_res->daytime);
            $hd['ttitle'] = p2h(html_entity_decode($a_res->ttitle, ENT_COMPAT, 'Shift_JIS'));
            $hd['itaj'] = p2h($a_res->itaj);

            if ($a_res->order < $this->resrange['start'] or $a_res->order > $this->resrange['to']) {
                continue;
            }

            $href_ht = "";
            if ($a_res->key) {
                if (empty($a_res->resnum) || $a_res->resnum == 1) {
                    $ls_q = '';
                    $footer_q = '#footer';
                } else {
                    $lf = max(1, $a_res->resnum - 0);
                    $ls_q = "&amp;ls={$lf}-";
                    $footer_q = "#r{$lf}";
                }
                if ($_conf['iphone']) {
                    $footer_q = '';
                }
                $time = time();
                $href_ht = $_conf['read_php'] . "?host=" . $a_res->host . "&amp;bbs=" . $a_res->bbs . "&amp;key=" . $a_res->key . $ls_q . "{$_conf['k_at_a']}&amp;nt={$time}{$footer_q}";
            }

            // 大きさ制限
            if (!$_GET['k_continue']) {
                $msg = $a_res->msg;
                if (strlen($msg) > $_conf['mobile.res_size']) {
                    $msg = substr($msg, 0, $_conf['mobile.ryaku_size']);

                    // 末尾に<br>があれば取り除く
                    if (substr($msg, -1) == ">") {
                        $msg = substr($msg, 0, strlen($msg)-1);
                    }
                    if (substr($msg, -1) == "r") {
                        $msg = substr($msg, 0, strlen($msg)-1);
                    }
                    if (substr($msg, -1) == "b") {
                        $msg = substr($msg, 0, strlen($msg)-1);
                    }
                    if (substr($msg, -1) == "<") {
                        $msg = substr($msg, 0, strlen($msg)-1);
                    }

                    $msg = $msg."  ";
                    $a_res->msg = $msg."<a href=\"read_res_hist?from={$a_res->order}&amp;end={$a_res->order}&amp;k_continue=1{$_conf['k_at_a']}\">略</a>";
                }
            }

            $res_ht = "[$a_res->order]"; // 番号
            $res_ht .= p2h($a_res->name) . ':'; // 名前
            // メール
            if ($a_res->mail) {
                $res_ht .= p2h($a_res->mail) . ':';
            }
            $res_ht .= "{$hd['daytime']}<br>\n"; // 日付とID
            $res_ht .= "<a href=\"{$_conf['subject_php']}?host={$a_res->host}&amp;bbs={$a_res->bbs}{$_conf['k_at_a']}\">{$hd['itaj']}</a> / ";
            if ($href_ht) {
                $res_ht .= "<a href=\"{$href_ht}\">{$hd['ttitle']}</a>\n";
            } elseif ($hd['ttitle']) {
                $res_ht .= "{$hd['ttitle']}\n";
            }

            // 削除
            //$res_ht = "<dt><input name=\"checked_hists[]\" type=\"checkbox\" value=\"{$a_res->order},,,,{$hd['daytime']}\"> ";
            $from_q = isset($_GET['from']) ? '&amp;from=' . $_GET['from'] : '';
            $dele_ht = "[<a href=\"read_res_hist.php?checked_hists[]={$a_res->order},,,," . rawurlencode($a_res->daytime) . "{$from_q}{$_conf['k_at_a']}\">削除</a>]";
            $res_ht .= $dele_ht;

            $res_ht .= '<br>';
            $res_ht .= "{$a_res->msg}<hr>\n"; // 内容

            echo $res_ht;
        }
    }

    // }}}
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
