<?php

// {{{ P2Client

/**
 * p2.2ch.net クライアント
 */
class P2Client
{
    // {{{ constants

    /**
     * Cookieを保存するSQLite3データベースのファイル名
     */
    const COOKIE_STORE_NAME = 'p2_2ch_net_cookies.sqlite3';

    /**
     * 各エントリポイントの名前
     */
    const SCRIPT_NAME_READ = 'read.php';
    const SCRIPT_NAME_POST = 'post.php';
    const SCRIPT_NAME_INFO = 'info.php';
    const SCRIPT_NAME_DAT  = 'dat.php';

    /**
     * User-Agent
     */
    const HTTP_USER_AGENT = 'PHP P2Client class';

    /**
     * HTTPリクエストのパラメータ名
     */
    const REQUEST_PARAMETER_LOGIN_ID    = 'form_login_id';
    const REQUEST_PARAMETER_LOGIN_PASS  = 'form_login_pass';
    const REQUEST_PARAMETER_LOGIN_REGIST_COOKIE = 'regist_cookie';
    const REQUEST_PARAMETER_LOGIN_IGNORE_COOKIE_ADDR = 'ignore_cip';
    const REQUEST_PARAMETER_HOST    = 'host';
    const REQUEST_PARAMETER_BBS     = 'bbs';
    const REQUEST_PARAMETER_KEY     = 'key';
    const REQUEST_PARAMETER_LS      = 'ls';
    const REQUEST_PARAMETER_NAME    = 'FROM';
    const REQUEST_PARAMETER_MAIL    = 'mail';
    const REQUEST_PARAMETER_MESSAGE = 'MESSAGE';
    const REQUEST_PARAMETER_POPUP   = 'popup';
    const REQUEST_PARAMETER_BERES   = 'submit_beres';
    const REQUEST_PARAMETER_CHARACTER_SET_DETECTION_HINT = 'detect_hint';

    /**
     * HTTPリクエストの固定パラメータ
     */
    const REQUEST_DATA_CHARACTER_SET_DETECTION_HINT = '◎◇';
    const REQUEST_DATA_LS_LAST1_NO_FIRST = 'l1n';

    /**
     * 読み込み正否判定のための文字列
     */
    const NEEDLE_READ_NO_THREAD = '<b>p2 info - 板サーバから最新のスレッド情報を取得できませんでした。</b>';
    const NEEDLE_DAT_NO_DAT = '<h4>p2 error: ご指定のDATはありませんでした</h4>';

    /**
     * 書き込み正否判定のための正規表現
     */
    const REGEX_POST_SUCCESS = '{<title>.*(?:書き(?:込|こ)みました|書き込み終了 - SubAll BBS).*</title>}is';
    const REGEX_POST_COOKIE  = '{<!-- 2ch_X:cookie -->|<title>■ 書き込み確認 ■</title>|>書き込み確認。<}';

    // }}}
    // {{{ properties

    /**
     * HTMLを読み込めなかったときの代替エンコーディング候補
     *
     * @var array
     */
    static private $_fallbackEncodings = array('Windows-31J', 'Shift_JIS-2004');

    /**
     * 公式p2のルートURI
     *
     * @var string
     */
    private $_rootUri;

    /**
     * p2.2ch.net/モリタポ ログインID (メールアドレス)
     *
     * @var string
     */
    private $_loginId;

    /**
     * p2.2ch.net/モリタポ ログインパスワード
     *
     * @var string
     */
    private $_loginPass;

    /**
     * p2.2ch.net Cookie認証時にIPアドレスの同一性をチェックしない
     *
     * @var bool
     */
    private $_ignoreCookieAddr;

    /**
     * Cookieを保存するKey-Value Storeオブジェクト
     *
     * @var P2KeyValueStore_Serializing
     */
    private $_cookieStore;

    /**
     * Cookieを管理するオブジェクト
     *
     * @var HTTP_Client_CookieManager
     */
    private $_cookieManager;

    /**
     * HTTPクライアントオブジェクト
     *
     * @var HTTP_Client
     */
    private $_httpClient;

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     *
     * @param string $rootUri
     * @param string $loginId
     * @param string $loginPass
     * @param string $cookieSaveDir
     * @param bool $ignoreCookieAddr
     * @throws P2Exception
     */
    public function __construct($rootUri,
                                $loginId,
                                $loginPass,
                                $cookieSaveDir,
                                $ignoreCookieAddr = false)
    {
        if (!preg_match('!^https?://.+/$!', $rootUri)) {
            throw new Exception('Invalid root URI was given.');
        }
        $this->_rootUri = $rootUri;

        try {
            $cookieSavePath = $cookieSaveDir . DIRECTORY_SEPARATOR . self::COOKIE_STORE_NAME;
            $cookieStore = P2KeyValueStore::getStore($cookieSavePath,
                                                     P2KeyValueStore::CODEC_SERIALIZING);
        } catch (Exception $e) {
            throw new P2Exception(get_class($e) . ': ' . $e->getMessage());
        }

        if ($cookieManager = $cookieStore->get($loginId)) {
            if (!($cookieManager instanceof HTTP_Client_CookieManager)) {
                $cookieStore->delete($loginId);
                throw new Exception('Cannot restore the cookie manager.');
            }
        } else {
            $cookieManager = new HTTP_Client_CookieManager();
        }

        $this->_loginId = $loginId;
        $this->_loginPass = $loginPass;
        $this->_cookieStore = $cookieStore;
        $this->_cookieManager = $cookieManager;
        $this->_ignoreCookieAddr = $ignoreCookieAddr;

        $defaultHeaders = array(
            'User-Agent' => self::HTTP_USER_AGENT,
        );
        $this->_httpClient = new HTTP_Client(null, $defaultHeaders, $cookieManager);
    }

    // }}}
    // {{{ destructor

    /**
     * データベースにCookieを保存する
     *
     * @param void
     */
    public function __destruct()
    {
        $this->_cookieStore->set($this->_loginId, $this->_cookieManager);
    }

    // }}}
    // {{{ login()

    /**
     * 公式p2にログインする
     *
     * @param string $uri
     * @param array $data
     * @param P2DOM $dom
     * @param DOMElement $form
     * @param mixed &$response
     * @return bool
     * @throws P2Exception
     */
    public function login($uri = null, array $data = array(),
                          P2DOM $dom = null, DOMElement $form = null,
                          &$response = null)
    {
        if ($uri === null) {
            $uri = $this->_rootUri;
        }

        if ($dom === null) {
            $response = $this->httpGet($uri);
            $dom = new P2DOM($response['body'], self::$_fallbackEncodings);
            $form = null;
        }

        if ($form === null) {
            $form = $this->getLoginForm($dom);
            if ($form === null) {
                throw new P2Exception('Login form not found.');
            }
        }

        $postData = array();
        foreach ($data as $name => $value) {
            $postData[$name] = rawurlencode($value);
        }
        $postData = $this->getFormValues($dom, $form, $postData);
        $postData[self::REQUEST_PARAMETER_LOGIN_ID] = rawurlencode($this->_loginId);
        $postData[self::REQUEST_PARAMETER_LOGIN_PASS] = rawurlencode($this->_loginPass);
        $postData[self::REQUEST_PARAMETER_LOGIN_REGIST_COOKIE] = '1';
        if ($this->_ignoreCookieAddr) {
            $postData[self::REQUEST_PARAMETER_LOGIN_IGNORE_COOKIE_ADDR] = '1';
        } elseif (array_key_exists(self::REQUEST_PARAMETER_LOGIN_IGNORE_COOKIE_ADDR, $postData)) {
            unset($postData[self::REQUEST_PARAMETER_LOGIN_IGNORE_COOKIE_ADDR]);
        }

        $response = $this->httpPost($uri, $postData, true);

        return $this->getLoginForm(new P2DOM($response['body'], self::$_fallbackEncodings)) === null;
    }

    // }}}
    // {{{ readThread()

    /**
     * スレッドを読む
     *
     * @param string $host
     * @param string $bbs
     * @param string $key
     * @param string $ls
     * @param mixed &$response
     * @return string HTTPレスポンスボディ
     * @throws P2Exception
     */
    public function readThread($host, $bbs, $key, $ls = '1', &$response = null)
    {
        $getData = $this->setupGetData($host, $bbs, $key, $ls);
        $uri = $this->_rootUri . self::SCRIPT_NAME_READ;
        $response = $this->httpGet($uri, $getData, true);
        $dom = new P2DOM($response['body'], self::$_fallbackEncodings);

        if ($form = $this->getLoginForm($dom)) {
            if (!$this->login($uri, $getData, $dom, $form, $response)) {
                throw new P2Exception('Login failed.');
            }
        }

        if (strpos($response['body'], self::NEEDLE_READ_NO_THREAD) !== false) {
            return null;
        }

        return $response['body'];
    }

    // }}}
    // {{{ downloadDat()

    /**
     * datを取り込む
     *
     * dat取得権限が無い場合は自動でモリタポを消費してdatを取得する。
     * 失敗しても泣かない。
     *
     * @param string $host
     * @param string $bbs
     * @param string $key
     * @param mixed &$response
     * @return string 生dat
     * @throws P2Exception
     */
    public function downloadDat($host, $bbs, $key, &$response = null)
    {
        // スレッドの有無を確かめるため、まず read.php を叩く。
        // dat落ち後にホストが移転した場合、移転後のホスト名でアクセスしても
        // スレッド情報を取得できなかったとのメッセージが表示される。
        $html = $this->readThread($host, $bbs, $key,
                                  self::REQUEST_DATA_LS_LAST1_NO_FIRST,
                                  $response);
        if ($html === null) {
            return null;
        }

        // 「モリタポでp2に取り込む」リンクの有無を調べる。
        // 無い場合はdat取得権限があるものとする。
        // dat取得権限がない場合やモリタポ通帳の残高が足りない場合の処理は端折る。
        $dom = new P2DOM($html, self::$_fallbackEncodings);
        $expression = './/a[contains(@href, "' . self::SCRIPT_NAME_READ . '?")'
                    . ' and contains(@href, "&moritapodat=")]';
        $result = $dom->query($expression);
        if (($result instanceof DOMNodeList) && $result->length > 0) {
            $anchor = $result->item(0);
            $uri = $this->_rootUri
                 . strstr($anchor->getAttribute('href'), self::SCRIPT_NAME_READ);
            $response = $this->httpGet($uri);
        }

        // datを取得する。
        $getData = $this->setupGetData($host, $bbs, $key);
        $uri = $this->_rootUri . self::SCRIPT_NAME_DAT;
        $response = $this->httpGet($uri, $getData, true);

        if (strpos($response['body'], self::NEEDLE_DAT_NO_DAT) !== false) {
            return null;
        }

        return $response['body'];
    }

    // }}}
    // {{{ post()

    /**
     * スレッドに書き込む
     *
     * @param string $host
     * @param string $bbs
     * @param string $key
     * @param string $name
     * @param string $mail
     * @param string $message
     * @param bool $beRes
     * @param mixed &$response
     * @return bool
     * @throws P2Exception
     */
    public function post($host, $bbs, $key, $name, $mail, $message,
                         $beRes = false, &$response = null)
    {
        // csrfIdを取得し、かつ公式p2の既読を最新の状態にするため、まず read.php を叩く。
        // 通信量を節約できるように ls=l1n としている。
        // popup=1 は書き込み後のページにリダイレクトさせないため。
        $html = $this->readThread($host, $bbs, $key,
                                  self::REQUEST_DATA_LS_LAST1_NO_FIRST,
                                  $response);
        if ($html === null) {
            return false;
        }

        $dom = new P2DOM($html, self::$_fallbackEncodings);
        $form = $this->getPostForm($dom);
        if ($form === null) {
            throw new P2Exception('Post form not found.');
        }

        // POSTするデータを用意。
        $postData = $this->setupPostData($dom, $form, $name, $mail, $message);
        $postData[self::REQUEST_PARAMETER_POPUP] = '1';
        if ($beRes) {
            $postData[self::REQUEST_PARAMETER_BERES] = '1';
        } elseif (array_key_exists(self::REQUEST_PARAMETER_BERES, $postData)) {
            unset($postData[self::REQUEST_PARAMETER_BERES]);
        }

        // POST実行。
        $uri = $this->_rootUri . self::SCRIPT_NAME_POST;
        $response = $this->httpPost($uri, $postData, true);

        // Cookie確認の場合は再POST。
        if (preg_match(self::REGEX_POST_COOKIE, $response['body'])) {
            // x-sjisはlibxml2で不明な文字セット扱いになり、
            // エラーは出ないが期待通りに読み込めないのでShift_JISを挿入しておく
            $html = preg_replace('/<head[^<>]*>/i',
                                 '$0<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">',
                                 $response['body']);
            $dom = new P2DOM($html, self::$_fallbackEncodings);
            $expression = './/form[contains(@action, "' . self::SCRIPT_NAME_POST . '")]';
            $result = $dom->query($expression);
            if (($result instanceof DOMNodeList) && $result->length > 0) {
                $postData = $this->setupPostData($dom, $result->item(0), $name, $mail, $message);
                $response = $this->httpPost($uri, $postData, true);
            } else {
                return false;
            }
        }

        return (bool)preg_match(self::REGEX_POST_SUCCESS, $response['body']);
    }

    // }}}
    // {{{ httpGet()

    /**
     * HTTP_Client::get() のラッパーメソッド
     *
     * @param string $uri
     * @param mixed $data
     * @param bool $preEncoded
     * @param array $headers
     * @return array HTTPレスポンス
     * @throws P2Exception
     */
    protected function httpGet($uri, $data = null, $preEncoded = false,
                               $headers = array())
    {
        $code = $this->_httpClient->get($uri, $data, $preEncoded, $headers);
        P2Exception::pearErrorToP2Exception($code);
        if ($code != 200) {
            throw new P2Exception('HTTP '. $code);
        }
        return $this->_httpClient->currentResponse();
    }

    // }}}
    // {{{ httpPost()

    /**
     * HTTP_Client::post() のラッパーメソッド
     *
     * @param string $uri
     * @param mixed $data
     * @param bool $preEncoded
     * @param array $files
     * @param array $headers
     * @return array HTTPレスポンス
     * @throws P2Exception
     */
    protected function httpPost($uri, $data, $preEncoded = false,
                                $files = array(), $headers = array())
    {
        $code = $this->_httpClient->post($uri, $data, $preEncoded, $files, $headers);
        P2Exception::pearErrorToP2Exception($code);
        if ($code != 200) {
            throw new P2Exception('HTTP '. $code);
        }
        return $this->_httpClient->currentResponse();
    }

    // }}}
    // {{{ getLoginForm()

    /**
     * ログインフォームを抽出する
     *
     * @paramP2DOM $dom
     * @return DOMElement|null
     */
    protected function getLoginForm(P2DOM $dom)
    {
        $result = $dom->query('.//form[@action and @id="login"]');
        if (($result instanceof DOMNodeList) && $result->length > 0) {
            return $result->item(0);
        }
        return null;
    }

    // }}}
    // {{{ getPostForm()

    /**
     * read.php/post_form.php の出力から書き込みフォームを抽出する
     *
     * @paramP2DOM $dom
     * @return DOMElement|null
     */
    protected function getPostForm(P2DOM $dom)
    {
        $result = $dom->query('.//form[@action and @id="resform"]');
        if (($result instanceof DOMNodeList) && $result->length > 0) {
            return $result->item(0);
        }
        return null;
    }

    // }}}
    // {{{ getFormValues()

    /**
     * フォームからinput要素を抽出し、連想配列を生成する
     *
     * select要素とtextarea要素は無視する。
     * また、<input type="checkbox" name="foo[]" value="bar"> のように
     * name属性で配列を指示しているものは正しく扱えない。
     * (このクラス自体がそういった要素を扱う必要のある場合を考慮していない)
     *
     * @param P2DOM $dom
     * @param DOMElement $form
     * @param array $data
     * @param bool $raw
     * @return array
     */
    protected function getFormValues(P2DOM $dom, DOMElement $form,
                                     array $data = array(), $raw = false)
    {
        $fields = $dom->query('.//input[@name and @value]', $form);
        foreach ($fields as $field) {
            $name = $field->getAttribute('name');
            $value = $field->getAttribute('value');
            if (!$raw) {
                $value = rawurlencode(mb_convert_encoding($value, 'SJIS-win', 'UTF-8'));
            }
            $data[$name] = $value;
        }

        return $data;
    }

    // }}}
    // {{{ setupGetData()

    /**
     * スレッドを読むための共通パラメータの配列を生成する
     *
     * @param string $host
     * @param string $bbs
     * @param string $key
     * @return array
     */
    protected function setupGetData($host, $bbs, $key, $ls = null)
    {
        $data = array(
            self::REQUEST_PARAMETER_HOST => rawurlencode($host),
            self::REQUEST_PARAMETER_BBS => rawurlencode($bbs),
            self::REQUEST_PARAMETER_KEY => rawurlencode($key),
        );
        if ($ls !== null) {
            $data[self::REQUEST_PARAMETER_LS] = rawurlencode($ls);
        }

        return $data;
    }

    // }}}
    // {{{ setupPostData()

    /**
     * スレッドに書き込むための共通パラメータの配列を生成する
     *
     * @param P2DOM $dom
     * @param DOMElement $form
     * @param string $key
     * @param string $name
     * @param string $mail
     * @param string $message
     * @return array
     */
    protected function setupPostData(P2DOM $dom, DOMElement $form,
                                     $name, $mail, $message)
    {
        $data = $this->getFormValues($dom, $form);
        $data[self::REQUEST_PARAMETER_CHARACTER_SET_DETECTION_HINT] =
            rawurlencode(self::REQUEST_DATA_CHARACTER_SET_DETECTION_HINT);
        $data[self::REQUEST_PARAMETER_NAME] = rawurlencode($name);
        $data[self::REQUEST_PARAMETER_MAIL] = rawurlencode($mail);
        $data[self::REQUEST_PARAMETER_MESSAGE] = rawurlencode($message);

        return $data;
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
