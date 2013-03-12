<?php

// {{{ SubjectTxt

/**
 * SubjectTxtクラス
 */
class SubjectTxt
{
    // {{{ properties

    public $host;
    public $bbs;
    public $subject_url;
    public $subject_file;
    public $subject_lines;
    public $storage;

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct($host, $bbs)
    {
        $this->host = $host;
        $this->bbs =  $bbs;
        $this->storage = 'file';

        $this->subject_file = P2Util::datDirOfHostBbs($host, $bbs) . 'subject.txt';
        $this->subject_url = 'http://' . $host . '/' . $bbs . '/subject.txt';

        // したらばのlivedoor移転に対応。読込先をlivedoorとする。
        $this->subject_url = P2Util::adjustHostJbbs($this->subject_url);

        // subject.txtをダウンロード＆セットする
        $this->dlAndSetSubject();
    }

    // }}}
    // {{{ dlAndSetSubject()

    /**
     * subject.txtをダウンロード＆セットする
     *
     * @return boolean セットできれば true、できなければ false
     */
    public function dlAndSetSubject()
    {
        $cont = $this->downloadSubject();
        if ($this->setSubjectLines($cont)) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ downloadSubject()

    /**
     * subject.txtをダウンロードする
     *
     * @return string subject.txt の中身
     */
    public function downloadSubject()
    {
        global $_conf;

        if ($this->storage === 'file') {
            FileCtl::mkdirFor($this->subject_file); // 板ディレクトリが無ければ作る

            if (file_exists($this->subject_file)) {
                if (!empty($_REQUEST['norefresh']) || (empty($_REQUEST['refresh']) && isset($_REQUEST['word']))) {
                    return;    // 更新しない場合は、その場で抜けてしまう
                } elseif (!empty($GLOBALS['expack.subject.multi-threaded-download.done'])) {
                    return;    // 並列ダウンロード済の場合も抜ける
                } elseif (empty($_POST['newthread']) and $this->isSubjectTxtFresh()) {
                    return;    // 新規スレ立て時でなく、更新が新しい場合も抜ける
                }
                $modified = http_date(filemtime($this->subject_file));
            } else {
                $modified = false;
            }
        }

        // DL
        $params = array();
        $params['timeout'] = $_conf['http_conn_timeout'];
        $params['readTimeout'] = array($_conf['http_read_timeout'], 0);
        if ($_conf['proxy_use']) {
            $params['proxy_host'] = $_conf['proxy_host'];
            $params['proxy_port'] = $_conf['proxy_port'];
        }
        $req = new HTTP_Request($this->subject_url, $params);
        $modified && $req->addHeader("If-Modified-Since", $modified);
        $req->addHeader('User-Agent', "Monazilla/1.00 ({$_conf['p2ua']})");

        $response = $req->sendRequest();

        if (PEAR::isError($response)) {
            $error_msg = $response->getMessage();
        } else {
            $code = $req->getResponseCode();
            if ($code == 302) {
                // ホストの移転を追跡
                $new_host = BbsMap::getCurrentHost($this->host, $this->bbs);
                if ($new_host != $this->host) {
                    $aNewSubjectTxt = new SubjectTxt($new_host, $this->bbs);
                    $body = $aNewSubjectTxt->downloadSubject();
                    return $body;
                }
            }
            if (!($code == 200 || $code == 206 || $code == 304)) {
                //var_dump($req->getResponseHeader());
                $error_msg = $code;
            }
        }

        if (isset($error_msg) && strlen($error_msg) > 0) {
            $url_t = P2Util::throughIme($this->subject_url);
            $info_msg_ht = "<p class=\"info-msg\">Error: {$error_msg}<br>";
            $info_msg_ht .= "rep2 info: <a href=\"{$url_t}\"{$_conf['ext_win_target_at']}>{$this->subject_url}</a> に接続できませんでした。</p>";
            P2Util::pushInfoHtml($info_msg_ht);
            $body = '';
        } else {
            $body = $req->getResponseBody();
        }

        // ■ DL成功して かつ 更新されていたら
        if ($body && $code != "304") {

            // したらば or be.2ch.net ならEUCをSJISに変換
            if (P2Util::isHostJbbsShitaraba($this->host) || P2Util::isHostBe2chNet($this->host)) {
                $body = mb_convert_encoding($body, 'CP932', 'CP51932');
            }

            if (FileCtl::file_write_contents($this->subject_file, $body) === false) {
                p2die('cannot write file');
            }
        } else {
            // touchすることで更新インターバルが効くので、しばらく再チェックされなくなる
            // （変更がないのに修正時間を更新するのは、少し気が進まないが、ここでは特に問題ないだろう）
            if ($this->storage === 'file') {
                touch($this->subject_file);
            }
        }

        return $body;
    }

    // }}}
    // {{{ isSubjectTxtFresh()

    /**
     * subject.txt が新鮮なら true を返す
     *
     * @return boolean 新鮮なら true。そうでなければ false。
     */
    public function isSubjectTxtFresh()
    {
        global $_conf;

        // キャッシュがある場合
        if (file_exists($this->subject_file)) {
            // キャッシュの更新が指定時間以内なら
            // clearstatcache();
            if (filemtime($this->subject_file) > time() - $_conf['sb_dl_interval']) {
                return true;
            }
        }

        return false;
    }

    // }}}
    // {{{ setSubjectLines()

    /**
     * subject.txt を読み込む
     *
     * 成功すれば、$this->subject_lines がセットされる
     *
     * @param string $cont これは eashm 用に渡している。
     * @return boolean 実行成否
     */
    public function setSubjectLines($cont = '')
    {
        $this->subject_lines = FileCtl::file_read_lines($this->subject_file);

        // JBBS@したらばなら重複スレタイを削除する
        if (P2Util::isHostJbbsShitaraba($this->host)) {
            $this->subject_lines = array_unique($this->subject_lines);
        }

        if ($this->subject_lines) {
            return true;
        } else {
            return false;
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
