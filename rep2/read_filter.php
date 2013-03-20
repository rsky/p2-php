<?php
/**
 * rep2expack - スレッド表示プリフィルタ
 *
 * SPMからのレスフィルタリングで使用
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

$popup_filter = 1;

function _read_filter_setup()
{
    $host = $_GET['host'];
    $bbs  = $_GET['bbs'];
    $key  = $_GET['key'];
    $resnum = (int)$_GET['resnum'];
    $field  = $_GET['field'];

    $aThread = new ThreadRead();
    $aThread->setThreadPathInfo($host, $bbs, $key);
    $aThread->readDat($aThread->keydat);

    $i = $resnum - 1;
    if (!($i >= 0 && $i < count($aThread->datlines) &&
          isset($_GET['rf']) && is_array($_GET['rf'])))
    {
        P2Util::pushInfoHtml('<p>フィルタリングの指定が変です。</p>');
        unset($_GET['rf'], $_REQUEST['rf']);
        return;
    }

    $ares = $aThread->datlines[$i];
    $resar = $aThread->explodeDatLine($ares);
    $name = $resar[0];
    $mail = $resar[1];
    $date_id = $resar[2];
    $msg = $resar[3];
    $params = $_GET['rf'];

    $include = ResFilter::INCLUDE_NONE;
    $fields = explode(':', $field);
    $field = array_shift($fields);
    if (in_array('refs', $fields)) {
        $include |= ResFilter::INCLUDE_REFERENCES;
    }
    if (in_array('refed', $fields)) {
        $include |= ResFilter::INCLUDE_REFERENCED;
    }
    $params['field'] = $field;
    $params['include'] = $include;

    $resFilter = ResFilter::configure($params);
    $target = $resFilter->getTarget($ares, $resnum, $name, $mail, $date_id, $msg);
    if ($field == 'date') {
        $date_part = explode(' ', trim($target));
        $word = $date_part[0];
    } else {
        $word = $target;
    }
    $params['word'] = $word;
    $_REQUEST['rf'] = $params;
}

_read_filter_setup();

// read.phpに処理を渡す
include P2_WWW_DIR . '/read.php';

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
