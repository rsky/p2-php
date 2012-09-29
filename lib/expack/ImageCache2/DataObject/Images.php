<?php

// {{{ ImageCache2_DataObject_Images

class ImageCache2_DataObject_Images extends ImageCache2_DataObject_Common
{
    // {{{ constants

    const OK     = 0;
    const ABORN  = 1;
    const BROKEN = 2;
    const LARGE  = 3;
    const VIRUS  = 4;

    // }}}
    // {{{ constcurtor

    public function __construct()
    {
        parent::__construct();
        $this->__table = $this->_ini['General']['table'];
    }

    // }}}
    // {{{ table()

    public function table()
    {
        return array(
            'id'   => DB_DATAOBJECT_INT,
            'uri'  => DB_DATAOBJECT_STR,
            'host' => DB_DATAOBJECT_STR,
            'name' => DB_DATAOBJECT_STR,
            'size' => DB_DATAOBJECT_INT,
            'md5'  => DB_DATAOBJECT_STR,
            'width'  => DB_DATAOBJECT_INT,
            'height' => DB_DATAOBJECT_INT,
            'mime' => DB_DATAOBJECT_STR,
            'time' => DB_DATAOBJECT_INT,
            'rank' => DB_DATAOBJECT_INT,
            'memo' => DB_DATAOBJECT_STR,
        );
    }

    // }}}
    // {{{ keys()

    public function keys()
    {
        return array('uri');
    }

    // }}}
    // {{{ ic2_isError()

    public function ic2_isError($url)
    {
        // ブラックリストをチェック
        $blacklist = new ImageCache2_DataObject_BlackList();
        if ($blacklist->get($url)) {
            switch ($blacklist->type) {
                case 0:
                    return 'x05'; // No More
                case 1:
                    return 'x01'; // Aborn
                case 2:
                    return 'x04'; // Virus
                default:
                    return 'x06'; // Unknown
            }
        }

        // エラーログをチェック
        if ($this->_ini['Getter']['checkerror']) {
            $errlog = new ImageCache2_DataObject_Errors();
            if ($errlog->get($url)) {
                return $errlog->errcode;
            }
        }

        return false;
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
