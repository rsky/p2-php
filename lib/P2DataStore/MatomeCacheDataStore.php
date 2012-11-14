<?php
/**
 * rep2expack - 新着まとめ読みキャッシュ管理クラス
 */

// {{{ MatomeCacheDataStore

class MatomeCacheDataStore extends AbstractDataStore
{
    // {{{ getKVS()

    /**
     * まとめ読みデータを保存するP2KeyValueStoreオブジェクトを取得する
     *
     * @param void
     * @return P2KeyValueStore
     */
    static public function getKVS()
    {
        return self::_getKVS($GLOBALS['_conf']['matome_db_path'],
                             P2KeyValueStore::CODEC_COMPRESSING);
    }

    // }}}
    // {{{ setRaw()

    /**
     * Codecによる変換なしでデータを保存する
     *
     * @param string $key
     * @param string $value
     * @return bool
     */
    static public function setRaw($key, $value)
    {
        $kvs = self::getKVS()->getRawKVS();
        if ($kvs->exists($key)) {
            return $kvs->update($key, $value);
        } else {
            return $kvs->set($key, $value);
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
