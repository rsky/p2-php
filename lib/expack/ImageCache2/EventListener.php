<?php
namespace ImageCache2;

use Doctrine\DBAL\Event\ConnectionEventArgs;

class EventListener
{
    public function postConnect(ConnectionEventArgs $eventArgs)
    {
        $conn = $eventArgs->getConnection();
        $driver = $eventArgs->getDriver();

        switch ($driver->getName()) {
            case 'mysqli':
            case 'pdo_mysql':
                $params = $conn->getParams();
                if (!isset($params['charset'])) {
                    $conn->query('SET NAMES utf8');
                }
                break;
            case 'pdo_pgsql':
                $conn->query("SET CLIENT_ENCODING TO 'UNICODE'");
                break;
        }
    }
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
