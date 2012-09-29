<?php
/**
 * rep2expack - ImageCache2 初期化スクリプト
 */
use Doctrine\Common\Cache,
    Doctrine\Common\EventManager,
    Doctrine\DBAL\Events,
    Doctrine\ORM\EntityManager,
    Doctrine\ORM\Tools\Setup,
    ImageCache2\EventListener;

// {{{ ic2_entitymanager()

/**
 * EntityManagerのインスタンスを取得する
 *
 * @param array $ini
 *
 * @return Doctrine\ORM\EntityManager
 */
function ic2_entitymanager(array $ini = null)
{
    static $defaultEntityManager = null;

    $useDefaultEntityManager = false;

    // デフォルトのEntityManagerを使う
    if (is_null($ini)) {
        if (!is_null($defaultEntityManager)) {
            return $defaultEntityManager;
        }
        $useDefaultEntityManager = true;
        $ini = ic2_loadconfig();
    }

    // Configurationをセットアップ
    if (defined('P2_CLI_RUN')) {
        $isDevMode = true;
        $proxyDir = null;
        $cache = null;
    } else {
        global $_conf;

        $isDevMode = !empty($ini['General']['devmode']);
        $proxyDir = $_conf['tmp_dir'];

        if (extension_loaded('apc')) {
            $cache = new Cache\ApcCache();
        } else {
            $cache = new Cache\FilesystemCache($_conf['cache_dir'] . '/doctrine');
        }
    }

    $config = Setup::createYAMLMetadataConfiguration(
        array(P2_CONFIG_DIR . '/ic2'), $isDevMode, $proxyDir, $cache);

    // EntityManagerのインスタンスを生成
    $conn = $ini['General']['database'];
    $eventManager = new EventManager();
    $eventManager->addEventListener(Events::postConnect, new EventListener);
    $entityManager = EntityManager::create($conn, $config, $eventManager);

    // デフォルトのEntityManagerを保存
    if ($useDefaultEntityManager) {
        $defaultEntityManager = $entityManager;
    }

    return $entityManager;
}

// }}}
// {{{ ic2_loadconfig()

/**
 * ユーザ設定読み込み関数
 *
 * @param void
 *
 * @return array
 */
function ic2_loadconfig()
{
    static $ini = null;

    if (!is_null($ini)) {
        return $ini;
    }

    $_conf = $GLOBALS['_conf'];

    include P2_CONFIG_DIR . '/conf_ic2.inc.php';

    $ini = array();
    $_ic2conf = preg_grep('/^expack\\.ic2\\.\\w+\\.\\w+$/', array_keys($_conf));

    foreach ($_ic2conf as $key) {
        $p = explode('.', $key);
        $cat = ucfirst($p[2]);
        $name = $p[3];
        if (!isset($ini[$cat])) {
            $ini[$cat] = array();
        }
        $ini[$cat][$name] = $_conf[$key];
    }

    if (!isset($ini['General']['database'])) {
        $ini['General']['database'] = ic2_convertdsn($ini['General']['dsn']);
    }

    return $ini;
}

// }}}
// {{{ ic2_convertdsn()

/**
 * PEAR::DBのDSNをDoctrine DBAL向けに変換する
 *
 * @param string $dsn
 *
 * @return array
 */
function ic2_convertdsn($dsn)
{
    if (!class_exists('DB', false)) {
        require 'DB.php';
    }

    $parsed = DB::parseDSN($dsn);
    $conn = array();

    $phptype = strtolower($parsed['phptype']);
    switch ($phptype) {
        case 'sqlite':
            p2die('sqlite2 is no longer supported');
            break;
        case 'mysqli':
            $conn['driver'] = 'mysqli';
            break;
        default:
            $conn['driver'] = 'pdo_' . $phptype;
    }

    if ($phptype === 'sqlite') {
        $conn['path'] = $parsed['database'];
    } else {
        $conn['dbname']   = $parsed['database'];
        $conn['user']     = $parsed['username'];
        $conn['password'] = $parsed['password'];

        if ($parsed['protocol'] == 'unix') {
            $conn['unix_socket'] = $parsed['sorcket'];
        } else {
            $conn['host'] = $parsed['hostspec'];
            if ($parsed['port']) {
                $conn['port'] = $parsed['port'];
            }
        }
    }

    return $conn;
}

// }}}
// {{{ ic2_findexec()

/**
 * 実行ファイル検索関数
 *
 * $search_pathから実行ファイル$commandを検索する
 * 見つかればパスをエスケープして返す（$escapeが偽ならそのまま返す）
 * 見つからなければfalseを返す
 *
 * @param string $command
 * @param string $search_path
 * @param bool $escape
 *
 * @return string
 */
function ic2_findexec($command, $search_path = '', $escape = true)
{
    // Windowsか、その他のOSか
    if (P2_OS_WINDOWS) {
        if (strtolower(strrchr($command, '.')) != '.exe') {
            $command .= '.exe';
        }
        $check = function_exists('is_executable') ? 'is_executable' : 'file_exists';
    } else {
        $check = 'is_executable';
    }

    // $search_pathが空のときは環境変数PATHから検索する
    if ($search_path == '') {
        $search_dirs = explode(PATH_SEPARATOR, getenv('PATH'));
    } else {
        $search_dirs = explode(PATH_SEPARATOR, $search_path);
    }

    // 検索
    foreach ($search_dirs as $path) {
        $path = realpath($path);
        if ($path === false || !is_dir($path)) {
            continue;
        }
        if ($check($path . DIRECTORY_SEPARATOR . $command)) {
            return ($escape ? escapeshellarg($command) : $command);
        }
    }

    // 見つからなかった
    return false;
}

// }}}
// {{{ ic2_load_class()

/**
 * クラスローダー
 *
 * @param string $name
 *
 * @return void
 */
function ic2_load_class($name)
{
    if (strncmp($name, 'ImageCache2_', 12) === 0) {
        include P2EX_LIB_DIR . '/' . str_replace('_', '/', $name) . '.php';
    } elseif (strncmp($name, 'ImageCache2\\', 12) === 0) {
        include P2EX_LIB_DIR . '/' . str_replace('\\', '/', $name) . '.php';
    } elseif (strncmp($name, 'Thumbnailer', 11) === 0) {
        include P2_LIB_DIR . '/' . str_replace('_', '/', $name) . '.php';
    }
}

// }}}

spl_autoload_register('ic2_load_class');

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
