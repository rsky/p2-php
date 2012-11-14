<?php
/**
 * rep2expack - 書き込みデータ管理クラス
 */

// {{{ PostDataStore

class PostDataStore extends AbstractDataStore
{
    // {{{ getKVS()

    /**
     * 書き込みデータを保存するP2KeyValueStoreオブジェクトを取得する
     *
     * @param void
     * @return P2KeyValueStore
     */
    static public function getKVS()
    {
        return self::_getKVS($GLOBALS['_conf']['post_db_path'],
                             P2KeyValueStore::CODEC_ARRAYSHIFTJIS);
    }

    // }}}
    // {{{ getKeyForBackup()

    /**
     * 書き込みバックアップのためのキーを取得する
     *
     * @param string $host
     * @param string $bbs
     * @param numeric $key
     * @param bool $newthread
     */
    static public function getKeyForBackup($host, $bbs, $key, $newthread = false)
    {
        if ($newthread) {
            $key = 'new';
        }
        return 'backup:' . self::_getKeySuffix($host, $bbs, $key);
    }

    // }}}
    // {{{ getKeyForConfig()

    /**
     * 板/スレごとの書き込み設定のためのキーを取得する
     *
     * @param string $host
     * @param string $bbs
     * @param numeric $key
     * @param bool $newthread
     */
    static public function getKeyForConfig($host, $bbs, $key = null)
    {
        if ($key === null) {
            $key = '';
        }
        return 'config:' . self::_getKeySuffix($host, $bbs, $key);
    }

    // }}}
    // {{{ _getKeySuffix()

    /**
     * キーの接尾辞を生成する
     *
     * @param string $host
     * @param string $bbs
     * @param string $key
     * @param bool $newthread
     */
    static private function _getKeySuffix($host, $bbs, $key)
    {
        global $_login;

        return rtrim($_login->user_u . P2Util::pathForHostBbs($host, $bbs) . $key, '/');
    }

    // }}}
    // {{{ clearBackup()

    /**
     * すべての書き込みバックアップまたは
     * 指定されたユーザーの書き込みバックアップを削除する
     *
     * @param string $user
     * @return int
     * @see AbstractDataStore::clear()
     */
    static public function clearBackup($user = null)
    {
        $prefix = 'backup:';
        if ($user !== null) {
            $prefix .= $user . '/';
        }
        return self::clear($prefix);
    }

    // }}}
    // {{{ clearConfig()

    /**
     * すべての書き込み設定または指定されたユーザーの書き込み設定を削除する
     *
     * @param string $user
     * @return int
     * @see AbstractDataStore::clear()
     */
    static public function clearConfig($user = null)
    {
        $prefix = 'config:';
        if ($user !== null) {
            $prefix .= $user . '/';
        }
        return self::clear($prefix);
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
