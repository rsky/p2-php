<?php
/**
 * ImageCache2 - メモから件数を取得する
 */

function getIC2ImageCount($key, $threshold = null) {
    require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';
    // 設定ファイル読み込み
    $ini = ic2_loadconfig();

    $icdb = new ImageCache2_DataObject_Images();
    // 閾値でフィルタリング
    if ($threshold === null) $threshold = $ini['Viewer']['threshold'];
    if (!($threshold == -1)) {
        $icdb->whereAddQuoted('rank', '>=', $threshold);
    }

    $db = $icdb->getDatabaseConnection();
    $db_class = strtolower(get_class($db));
    $keys = explode(' ', ImageCache2_Normalizer::normalize($key, 'CP932'));
    foreach ($keys as $k) {
        $operator = 'LIKE';
        $wildcard = '%';
        $not = false;
        if ($k[0] == '-' && strlen($k) > 1) {
            $not = true;
            $k = substr($k, 1);
        }
        if (strpos($k, '%') !== false || strpos($k, '_') !== false) {
            // SQLite2はLIKE演算子の右辺でバックスラッシュによるエスケープや
            // ESCAPEでエスケープ文字を指定することができないのでGLOB演算子を使う
            if ($db_class == 'db_sqlite') {
                if (strpos($k, '*') !== false || strpos($k, '?') !== false) {
                    throw new InvalidArgumentException('「%または_」と「*または?」が混在するキーワードは使えません。');
                } else {
                    $operator = 'GLOB';
                    $wildcard = '*';
                }
            } else {
                $k = preg_replace('/[%_]/', '\\\\$0', $k);
            }
        }
        $expr = $wildcard . $k . $wildcard;
        if ($not) {
            $operator = 'NOT ' . $operator;
        }
        $icdb->whereAddQuoted('memo', $operator, $expr);
    }

    $sql = sprintf('SELECT COUNT(*) FROM %s %s', $db->quoteIdentifier($ini['General']['table']), $icdb->_query['condition']);
    $all = $db->getOne($sql);
    if (DB::isError($all)) {
        throw new InvalidArgumentException($all->getMessage());
    }
    return $all;
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
