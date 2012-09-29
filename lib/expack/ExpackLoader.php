<?php
// {{{ ExpackLoader

/**
 * 拡張パック初期化クラス
 *
 * @static
 */
class ExpackLoader
{
    // {{{ loadFunction()

    /**
     * 関数をロードする
     *
     * @param   string  $funcName   関数名
     * @param   string  $fileName   関数が定義されているファイル名
     * @return  void
     */
    static public function loadFunction($funcName, $fileName)
    {
        if (!function_exists($funcName)) {
            include P2EX_LIB_DIR . '/' . $fileName;
        }
    }

    // }}}
    // {{{ loadClass()

    /**
     * クラスをロードする
     *
     * @param   string  $className  クラス名
     * @param   string  $fileName   クラスが定義されているファイル名
     * @return  void
     */
    static public function loadClass($className, $fileName)
    {
        if (!class_exists($className, false)) {
            include P2EX_LIB_DIR . '/' . $fileName;
        }
    }

    // }}}
    // {{{ loadActiveMona()

    /**
     * アクティブモナーの準備をする
     */
    static public function loadActiveMona()
    {
        global $_conf;

        if (defined('P2_ACTIVEMONA_AVAILABLE')) {
            return;
        }

        if ((!$_conf['ktai'] && $_conf['expack.am.enabled']) ||
            ($_conf['ktai'] && $_conf['expack.am.enabled'] && $_conf['expack.am.autong_k'])
        ) {
            self::loadClass('ActiveMona', 'ActiveMona.php');
            define('P2_ACTIVEMONA_AVAILABLE', 1);
        } else {
            define('P2_ACTIVEMONA_AVAILABLE', 0);
        }
    }

    // }}}
    // {{{ initActiveMona()

    /**
     * スレッド表示オブジェクトにアクティブモナーで使う変数をアサインする
     */
    static public function initActiveMona($aShowThread)
    {
        global $_conf;

        $aShowThread->activeMona = ActiveMona::singleton();
        $aShowThread->am_enabled = true;

        if (!$_conf['ktai']) {
            if ($_conf['expack.am.autodetect']) {
                $aShowThread->am_autodetect = true;
            }
            if ($_conf['expack.am.display'] == 0) {
                $aShowThread->am_side_of_id = true;
            } elseif ($_conf['expack.am.display'] == 1) {
                $aShowThread->am_on_spm = true;
            } elseif ($_conf['expack.am.display'] == 2) {
                $aShowThread->am_side_of_id = true;
                $aShowThread->am_on_spm = true;
            }
        } elseif ($_conf['expack.am.autong_k']) {
            $aShowThread->am_autong = true;
        }
    }

    // }}}
    // {{{ loadImageCache()

    /**
     * ImageCache2の準備をする
     */
    static public function loadImageCache()
    {
        global $_conf;

        if (defined('P2_IMAGECACHE_AVAILABLE')) {
            return;
        }

        if ((!$_conf['ktai'] && $_conf['expack.ic2.enabled'] % 2 == 1) ||
            ($_conf['ktai'] && $_conf['expack.ic2.enabled'] >= 2))
        {
            self::loadFunction('ic2_loadconfig', 'ImageCache2/bootstrap.php');
            define('P2_IMAGECACHE_AVAILABLE', 2);
        } else {
            define('P2_IMAGECACHE_AVAILABLE', 0);
        }
    }

    // }}}
    // {{{ loadAAS()

    /**
     * AASの準備をする
     */
    static public function loadAAS()
    {
        global $_conf;

        if (defined('P2_AAS_AVAILABLE')) {
            return;
        }

        if ($_conf['expack.aas.enabled']) {
            if ($_conf['expack.aas.inline_enabled']) {
                define('P2_AAS_AVAILABLE', 2);
            } else {
                define('P2_AAS_AVAILABLE', 1);
            }
        } else {
            define('P2_AAS_AVAILABLE', 0);
        }
    }

    // }}}
    // {{{ initImageCache()

    /**
     * スレッド表示オブジェクトにImageCache2で使う変数をアサインする
     */
    static public function initImageCache($aShowThread)
    {
        global $_conf;

        $dpr = 1.0;

        if (!$_conf['ktai']) {
            $aShowThread->thumb_id_suffix = '-' . strtr(microtime(), '. ', '--');
            $aShowThread->thumbnailer = new ImageCache2_Thumbnailer(ImageCache2_Thumbnailer::SIZE_PC);
        } else {
            $preview_size = ImageCache2_Thumbnailer::SIZE_PC;
            $thumb_size = ImageCache2_Thumbnailer::SIZE_MOBILE;
            if ($_conf['iphone'] && !empty($_SESSION['device_pixel_ratio'])) {
                $dpr = $_SESSION['device_pixel_ratio'];
                if ($dpr === 1.5) {
                    $preview_size |= ImageCache2_Thumbnailer::DPR_1_5;
                    $thumb_size   |= ImageCache2_Thumbnailer::DPR_1_5;
                } elseif ($dpr === 2.0) {
                    $preview_size |= ImageCache2_Thumbnailer::DPR_2_0;
                    $thumb_size   |= ImageCache2_Thumbnailer::DPR_2_0;
                } else {
                    $dpr = 1.0;
                }
            }
            $aShowThread->inline_prvw = new ImageCache2_Thumbnailer($preview_size);
            $aShowThread->thumbnailer = new ImageCache2_Thumbnailer($thumb_size);
        }

        if ($aShowThread->thumbnailer->ini['General']['automemo']) {
            $aShowThread->img_memo = ImageCache2_Noramlizer::normalize($aShowThread->thread->ttitle, 'CP932');
            $aShowThread->img_memo_query = '&amp;memo=' . rawurlencode($aShowThread->img_memo);
            $aShowThread->img_memo_query .= '&amp;' . $_conf['detect_hint_q_utf8'];
        } else {
            $aShowThread->img_memo = null;
            $aShowThread->img_memo_query = '';
        }

        $aShowThread->img_dpr = $dpr;
        if ($dpr === 1.5 || $dpr === 2.0) {
            $aShowThread->img_dpr_query = '&amp;d=' . $dpr;
        } else {
            $aShowThread->img_dpr_query = '';
        }

        self::loadClass('ImageCache2_Switch', 'ImageCache2/Switch.php');
        if (!ImageCache2_Switch::get($_conf['ktai'])) {
            $GLOBALS['pre_thumb_limit'] = 0;
            $GLOBALS['pre_thumb_limit_k'] = 0;
            $GLOBALS['pre_thumb_unlimited'] = false;
            $GLOBALS['pre_thumb_ignore_limit'] = false;
            $_conf['expack.ic2.newres_ignore_limit'] = false;
            $_conf['expack.ic2.newres_ignore_limit_k'] = false;
        }
    }

    // }}}
    // {{{ initAAS()

    /**
     * スレッド表示オブジェクトにAASで使う変数をアサインする
     */
    static public function initAAS($aShowThread)
    {
        global $_conf;

        if ($_conf['iphone']) {
            $aShowThread->aas_rotate = '&#x21BB;';
        } elseif ($_conf['ktai']) {
            $mobile = Net_UserAgent_Mobile::singleton();
            /**
             * @link http://www.nttdocomo.co.jp/service/imode/make/content/pictograph/
             * @link http://www.au.kddi.com/ezfactory/tec/spec/3.html
             * @link http://mb.softbank.jp/mb/service/3G/mail/pictogram/
             */
            if ($mobile->isDoCoMo()) {
                $aShowThread->aas_rotate = '&#xF9DA;';      // リサイクル, 拡42
            } elseif ($mobile->isEZweb()) {
                $aShowThread->aas_rotate = '&#xF47D;';      // 循環矢印, 807
            } elseif ($mobile->isSoftBank()) {
                $aShowThread->aas_rotate = "\x1b\$Pc\x0f";  // 渦巻, 414
            }
        } else {
            //
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
