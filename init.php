<?php
if (file_exists(__DIR__ . '/p2pear.phar')) {
    set_include_path('phar://' . __DIR__ . '/p2pear.phar'
                     . PATH_SEPARATOR . get_include_path());
}
error_reporting(E_ALL & ~(E_NOTICE | E_STRICT | E_DEPRECATED));
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/conf/conf.inc.php';
