<?php
namespace ImageCache2\Entity;

class BlackList
{
    // {{{ constants

    const NOMORE = 0;
    const ABORN  = 1;
    const VIRUS  = 2;

    // }}}
    // {{{ properties

    protected $id;
    protected $uri;
    protected $size;
    protected $md5;
    protected $type;

    // }}}
}

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
