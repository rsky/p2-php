<?php
/**
 * rep2expack - 新着まとめ読みキャッシュのメタデータ管理クラス
 */

// {{{ MatomeCacheMetaDataStore

class MatomeCacheMetaDataStore extends AbstractDataStore
{
    // {{{ getKVS()

    /**
     * まとめ読みメタデータを保存するP2KeyValueStoreオブジェクトを取得する
     *
     * @param void
     * @return P2KeyValueStore
     */
    static public function getKVS()
    {
        return self::_getKVS($GLOBALS['_conf']['matome_db_path'],
                             P2KeyValueStore::CODEC_ARRAYSHIFTJIS);
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
