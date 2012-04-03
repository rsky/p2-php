<?php
/**
 * rep2 - �X���b�h�\���X�N���v�g
 * �t���[��������ʁA�E������
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ���[�U�F��

// +Wiki
require_once P2_LIB_DIR . '/wiki/read.inc.php';

// iPhone
if ($_conf['iphone']) {
    include P2_LIB_DIR . '/toolbar_i.inc.php';
    define('READ_HEADER_INC_PHP', P2_LIB_DIR . '/read_header_i.inc.php');
    define('READ_FOOTER_INC_PHP', P2_LIB_DIR . '/read_footer_i.inc.php');
// �g��
} elseif ($_conf['ktai']) {
    define('READ_HEADER_INC_PHP', P2_LIB_DIR . '/read_header_k.inc.php');
    define('READ_FOOTER_INC_PHP', P2_LIB_DIR . '/read_footer_k.inc.php');
// PC
} else {
    define('READ_HEADER_INC_PHP', P2_LIB_DIR . '/read_header.inc.php');
    define('READ_FOOTER_INC_PHP', P2_LIB_DIR . '/read_footer.inc.php');
}

//================================================================
// �ϐ�
//================================================================
$newtime = date('gis');  // ���������N���N���b�N���Ă��ēǍ����Ȃ��d�l�ɑ΍R����_�~�[�N�G���[
// $_today = date('y/m/d');
$is_ajax = !empty($_GET['ajax']);

//=================================================
// �X���̎w��
//=================================================
detectThread();    // global $host, $bbs, $key, $ls

//=================================================
// ���X�t�B���^
//=================================================
$do_filtering = false;
if (array_key_exists('rf', $_REQUEST) && is_array($_REQUEST['rf'])) {
    $resFilter = ResFilter::configure($_REQUEST['rf']);
    if ($resFilter->hasWord()) {
        $do_filtering = true;
        if ($_conf['ktai']) {
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
            $resFilter->setRange($_conf['mobile.rnum_range'], $page);
        }
        if (empty($popup_filter) && isset($_REQUEST['submit_filter'])) {
            $resFilter->save();
        }
    }
} else {
    $resFilter = ResFilter::restore();
}

//=================================================
// ���ځ[��&NG���[�h�ݒ�ǂݍ���
//=================================================
$GLOBALS['ngaborns'] = NgAbornCtl::loadNgAborns();

//==================================================================
// ���C��
//==================================================================

if (!isset($aThread)) {
    $aThread = new ThreadRead();
}

// ls�̃Z�b�g
if (!empty($ls)) {
    $aThread->ls = mb_convert_kana($ls, 'a');
}

//==========================================================
// idx�̓ǂݍ���
//==========================================================

// host�𕪉�����idx�t�@�C���̃p�X�����߂�
if (!isset($aThread->keyidx)) {
    $aThread->setThreadPathInfo($host, $bbs, $key);
}

// �f�B���N�g����������΍��
FileCtl::mkdirFor($aThread->keyidx);
FileCtl::mkdirFor($aThread->keydat);

$aThread->itaj = P2Util::getItaName($host, $bbs);
if (!$aThread->itaj) { $aThread->itaj = $aThread->bbs; }

// idx�t�@�C��������Γǂݍ���
if ($lines = FileCtl::file_read_lines($aThread->keyidx, FILE_IGNORE_NEW_LINES)) {
    $idx_data = explode('<>', $lines[0]);
} else {
    $idx_data = array_fill(0, 12, '');
}
$aThread->getThreadInfoFromIdx();

//==========================================================
// preview >>1
//==========================================================

//if (!empty($_GET['onlyone'])) {
if (!empty($_GET['one'])) {
    $aThread->ls = '1';
    $aThread->resrange = array('start' => 1, 'to' => 1, 'nofirst' => false);

    // �K���������m�ł͂Ȃ����֋X�I��
    //if (!isset($aThread->rescount) && !empty($_GET['rc'])) {
    if (!isset($aThread->rescount) && !empty($_GET['rescount'])) {
        //$aThread->rescount = $_GET['rc'];
        $aThread->rescount = (int)$_GET['rescount'];
    }

    $preview = $aThread->previewOne();
    $ptitle_ht = p2h($aThread->itaj) . ' / ' . $aThread->ttitle_hd;

    include READ_HEADER_INC_PHP;
    echo $preview;
    include READ_FOOTER_INC_PHP;

    return;
}

//===========================================================
// DAT�̃_�E�����[�h
//===========================================================
$offline = !empty($_GET['offline']);

if (!$offline) {
    $aThread->downloadDat();
}

// DAT��ǂݍ���
$aThread->readDat();

// �I�t���C���w��ł����O���Ȃ���΁A���߂ċ����ǂݍ���
if (empty($aThread->datlines) && $offline) {
    $aThread->downloadDat();
    $aThread->readDat();
}

// �^�C�g�����擾���Đݒ�
$aThread->setTitleFromLocal();

//===========================================================
// �\�����X�Ԃ͈̔͂�ݒ�
//===========================================================
if ($_conf['ktai']) {
    $before_respointer = $_conf['mobile.before_respointer'];
} else {
    $before_respointer = $_conf['before_respointer'];
}

// �擾�ς݂Ȃ�
if ($aThread->isKitoku()) {

    //�u�V�����X�̕\���v�̎��͓��ʂɂ�����ƑO�̃��X����\��
    if (!empty($_GET['nt'])) {
        if (substr($aThread->ls, -1) == '-') {
            $n = $aThread->ls - $before_respointer;
            if ($n < 1) { $n = 1; }
            $aThread->ls = $n . '-';
        }

    } elseif (!$aThread->ls) {
        $from_num = $aThread->readnum +1 - $_conf['respointer'] - $before_respointer;
        if ($from_num < 1) {
            $from_num = 1;
        } elseif ($from_num > $aThread->rescount) {
            $from_num = $aThread->rescount - $_conf['respointer'] - $before_respointer;
        }
        $aThread->ls = $from_num . '-';
    }

    if ($_conf['ktai'] && strpos($aThread->ls, 'n') === false) {
        $aThread->ls = $aThread->ls . 'n';
    }

// ���擾�Ȃ�
} else {
    if (!$aThread->ls) {
        $aThread->ls = $_conf['get_new_res_l'];
    }
}

// �t�B���^�����O�̎��́Aall�Œ�Ƃ���
if ($resFilter && $resFilter->hasWord()) {
    $aThread->ls = 'all';
}

$aThread->lsToPoint();

//===============================================================
// �v�����g
//===============================================================
$ptitle_ht = p2h($aThread->itaj) . ' / ' . $aThread->ttitle_hd;

if ($_conf['ktai']) {

    if ($resFilter && $resFilter->hasWord() && $aThread->rescount) {
        $GLOBALS['filter_hits'] = 0;
    } else {
        $GLOBALS['filter_hits'] = null;
    }

    $aShowThread = new ShowThreadK($aThread);

    if ($is_ajax) {
        $response = trim(mb_convert_encoding($aShowThread->getDatToHtml(true), 'UTF-8', 'CP932'));
        if (isset($_GET['respop_id'])) {
            $response = preg_replace('/<[^<>]+? id="/u', sprintf('$0_respop%d_', $_GET['respop_id']), $response);
        }
        /*if ($_conf['iphone']) {
            // HTML�̒f�Ђ�XML�Ƃ��ēn���Ă�DOM��id��class�����Ғʂ�ɔ��f����Ȃ�
            header('Content-Type: application/xml; charset=UTF-8');
            //$responseId = 'ajaxResponse' . time();
            $doc = new DOMDocument();
            $err = error_reporting(E_ALL & ~E_WARNING);
            $html = '<html><head>'
                  . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
                  . '</head><body>'
                  . $response
                  . '</body></html>';
            $doc->loadHTML($html);
            error_reporting($err);
            echo '<?xml version="1.0" encoding="utf-8" ?>';
            echo $doc->saveXML($doc->getElementsByTagName('div')->item(0));
        } else {*/
            // ����āAHTML�̒f�Ђ����̂܂ܕԂ���innterHTML�ɑ�����Ȃ��Ƃ����Ȃ��B
            // (���{�I�Ƀ��X�|���X�̃t�H�[�}�b�g�ƃN���C�A���g���ł̏�����ς��Ȃ������)
            header('Content-Type: text/html; charset=UTF-8');
            echo $response;
        //}
    } else {
        if ($aThread->rescount) {
            if ($_GET['showbl']) {
                $content = $aShowThread->getDatToHtml_resFrom();
            } else {
                $content = $aShowThread->getDatToHtml();
            }
        } elseif ($aThread->diedat && count($aThread->datochi_residuums) > 0) {
            $content = $aShowThread->getDatochiResiduums();
        }

        include READ_HEADER_INC_PHP;

        if ($_conf['iphone'] && $_conf['expack.spm.enabled']) {
            echo $aShowThread->getSpmObjJs();
        }

        echo $content;

        include READ_FOOTER_INC_PHP;
    }

} else {

    // �w�b�_ �\��
    include READ_HEADER_INC_PHP;
    flush();

    //===========================================================
    // ���[�J��Dat��ϊ�����HTML�\��
    //===========================================================
    // ���X������A�����w�肪�����
    if ($resFilter && $resFilter->hasWord() && $aThread->rescount) {

        $all = $aThread->rescount;

        $GLOBALS['filter_hits'] = 0;

        echo "<p><b id=\"filterstart\">{$all}���X�� <span id=\"searching\">n</span>���X���q�b�g</b></p>\n";
    }
    if ($_GET['showbl']) {
        echo  '<p><b>' . p2h($aThread->resrange['start']) . '�ւ̃��X</b></p>';
    }

    //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection("datToHtml");

    if ($aThread->rescount) {
        $mainhtml = '';
        $aShowThread = new ShowThreadPc($aThread);

        if ($_conf['expack.spm.enabled']) {
            echo $aShowThread->getSpmObjJs();
        }

        $res1 = $aShowThread->quoteOne(); // >>1�|�b�v�A�b�v�p
        if ($_conf['coloredid.enable'] > 0 && $_conf['coloredid.click'] > 0 &&
            $_conf['coloredid.rate.type'] > 0) {
            if ($_GET['showbl']) {
                $mainhtml = $aShowThread->datToHtml_resFrom(true);
            } else {
                $mainhtml .= $aShowThread->datToHtml(true);
            }
            $mainhtml .= $res1['q'];
        } else {
            if ($_GET['showbl']) {
                $aShowThread->datToHtml_resFrom();
            } else {
                $aShowThread->datToHtml();
            }
            echo $res1['q'];
        }


        // ���X�ǐՃJ���[
        if ($_conf['backlink_coloring_track']) {
            echo $aShowThread->getResColorJs();
        }

        // ID�J���[�����O
        if ($_conf['coloredid.enable'] > 0 && $_conf['coloredid.click'] > 0) {
            echo $aShowThread->getIdColorJs();
            // �u���E�U���׌y���̂��߁ACSS���������X�N���v�g�̌�ŃR���e���c��
            // �����_�����O������
            echo $mainhtml;
        }

        // �O���c�[��
        $pluswiki_js = '';

        if ($_conf['wiki.idsearch.spm.mimizun.enabled']) {
            if (!class_exists('Mimizun', false)) {
                require P2_PLUGIN_DIR . '/mimizun/Mimizun.php';
            }
            $mimizun = new Mimizun();
            $mimizun->host = $aThread->host;
            $mimizun->bbs  = $aThread->bbs;
            if ($mimizun->isEnabled()) {
                $pluswiki_js .= "WikiTools.addMimizun({$aShowThread->spmObjName});";
            }
        }

        if ($_conf['wiki.idsearch.spm.hissi.enabled']) {
            if (!class_exists('Hissi', false)) {
                require P2_PLUGIN_DIR . '/hissi/Hissi.php';
            }
            $hissi = new Hissi();
            $hissi->host = $aThread->host;
            $hissi->bbs  = $aThread->bbs;
            if ($hissi->isEnabled()) {
                $pluswiki_js .= "WikiTools.addHissi({$aShowThread->spmObjName});";
            }
        }

        if ($_conf['wiki.idsearch.spm.stalker.enabled']) {
            if (!class_exists('Stalker', false)) {
                require P2_PLUGIN_DIR . '/stalker/Stalker.php';
            }
            $stalker = new Stalker();
            $stalker->host = $aThread->host;
            $stalker->bbs  = $aThread->bbs;
            if ($stalker->isEnabled()) {
                $pluswiki_js .= "WikiTools.addStalker({$aShowThread->spmObjName});";
            }
        }

        if ($pluswiki_js !== '') {
            echo <<<EOP
<script type="text/javascript">
//<![CDATA[
{$pluswiki_js}
//]]>
</script>
EOP;
        }

    } elseif ($aThread->diedat && count($aThread->datochi_residuums) > 0) {
        require_once P2_LIB_DIR . '/ShowThreadPc.php';
        $aShowThread = new ShowThreadPc($aThread);
        echo $aShowThread->getDatochiResiduums();
    }

    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection("datToHtml");

    // �t�B���^���ʂ�\��
    if ($resFilter && $resFilter->hasWord() && $aThread->rescount) {
        echo <<<EOP
<script type="text/javascript">
//<![CDATA[
var filterstart = document.getElementById('filterstart');
if (filterstart) {
    filterstart.style.backgroundColor = 'yellow';
    filterstart.style.fontWeight = 'bold';
}
//]]>
</script>\n
EOP;
        if ($GLOBALS['filter_hits'] > 5) {
            echo "<p><b class=\"filtering\">{$all}���X�� {$GLOBALS['filter_hits']}���X���q�b�g</b></p>\n";
        }
    }

    // �t�b�^ �\��
    include READ_FOOTER_INC_PHP;
}
flush();

//===========================================================
// idx�̒l��ݒ�A�L�^
//===========================================================
if ($aThread->rescount) {

    // �����̎��́A���ǐ����X�V���Ȃ�
    if ((isset($GLOBALS['word']) && strlen($GLOBALS['word']) > 0) || $is_ajax) {
        $aThread->readnum = $idx_data[5];
    } else {
        $aThread->readnum = min($aThread->rescount, max(0, $idx_data[5], $aThread->resrange['to']));
    }
    $newline = $aThread->readnum + 1; // $newline�͔p�~�\�肾���A���݊��p�ɔO�̂���

    $sar = array($aThread->ttitle, $aThread->key, $idx_data[2], $aThread->rescount, '',
                 $aThread->readnum, $idx_data[6], $idx_data[7], $idx_data[8], $newline,
                 $idx_data[10], $idx_data[11], $aThread->datochiok);
    P2Util::recKeyIdx($aThread->keyidx, $sar); // key.idx�ɋL�^
}

//===========================================================
// �������L�^
//===========================================================
if ($aThread->rescount && !$is_ajax) {
    recRecent(implode('<>', array($aThread->ttitle, $aThread->key, $idx_data[2], '', '',
                                  $aThread->readnum, $idx_data[6], $idx_data[7], $idx_data[8], $newline,
                                  $aThread->host, $aThread->bbs)));
}

// NG���ځ[����L�^
NgAbornCtl::saveNgAborns();

// �ȏ� ---------------------------------------------------------------
exit;

//===============================================================================
// �֐�
//===============================================================================
// {{{ detectThread()

/**
 * �X���b�h���w�肷��
 */
function detectThread()
{
    global $_conf, $host, $bbs, $key, $ls;

    list($nama_url, $host, $bbs, $key, $ls) = P2Util::detectThread();

    if (!($host && $bbs && $key)) {
        if ($nama_url) {
            $nama_url = p2h($nama_url);
            p2die('�X���b�h�̎w�肪�ςł��B', "<a href=\"{$nama_url}\">{$nama_url}</a>", true);
        } else {
            p2die('�X���b�h�̎w�肪�ςł��B');
        }
    }
}

// }}}
// {{{ recRecent()

/**
 * �������L�^����
 */
function recRecent($data)
{
    global $_conf;

    $lock = new P2Lock($_conf['recent_idx'], false);

    // $_conf['recent_idx'] �t�@�C�����Ȃ���ΐ���
    FileCtl::make_datafile($_conf['recent_idx']);

    $lines = FileCtl::file_read_lines($_conf['recent_idx'], FILE_IGNORE_NEW_LINES);
    $neolines = array();

    // {{{ �ŏ��ɏd���v�f���폜���Ă���

    if (is_array($lines)) {
        foreach ($lines as $l) {
            $lar = explode('<>', $l);
            $data_ar = explode('<>', $data);
            if ($lar[1] == $data_ar[1]) { continue; } // key�ŏd�����
            if (!$lar[1]) { continue; } // key�̂Ȃ����͕̂s���f�[�^
            $neolines[] = $l;
        }
    }

    // }}}

    // �V�K�f�[�^�ǉ�
    array_unshift($neolines, $data);

    while (sizeof($neolines) > $_conf['rct_rec_num']) {
        array_pop($neolines);
    }

    // {{{ ��������

    if ($neolines) {
        $cont = '';
        foreach ($neolines as $l) {
            $cont .= $l . "\n";
        }

        if (FileCtl::file_write_contents($_conf['recent_idx'], $cont) === false) {
            p2die('cannot write file.');
        }
    }

    // }}}

    return true;
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
