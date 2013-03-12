<?php

// {{{ P2Exception

/**
 * 例外ラッパークラス
 */
class P2Exception extends Exception
{
    // {{{ pearErrorToP2Exception()

    /**
     * @param mixed $value
     * @param string $errorMessagePrefix
     * @return void
     * @throws P2Exception
     */
    static public function pearErrorToP2Exception($value, $errorMessagePrefix = '')
    {
        if (PEAR::isError($value)) {
            $message = $errorMessagePrefix . ': '
                     . get_class($value) . ': ' . $value->getMessage();
            throw new P2Exception($message);
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
