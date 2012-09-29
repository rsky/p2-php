<?php
namespace ImageCache2\Entity;

class Image
{
    // {{{ constants

    const OK     = 0;
    const ABORN  = 1;
    const BROKEN = 2;
    const LARGE  = 3;
    const VIRUS  = 4;

    // }}}
    // {{{ properties

    protected $id;
    protected $uri;
    protected $host;
    protected $name;
    protected $size;
    protected $md5;
    protected $width;
    protected $height;
    protected $mime;
    protected $time;
    protected $rank;
    protected $memo;

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
