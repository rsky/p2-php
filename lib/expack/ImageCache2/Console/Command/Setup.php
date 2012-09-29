<?php

namespace ImageCache2\Console\Command;

use Symfony\Component\Console\Command\Command as sfConsoleCommand;
use Doctrine\DBAL\Connection,
    Doctrine\DBAL\DBALException,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Formatter\OutputFormatterStyle,
    Symfony\Component\Yaml\Yaml;

require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';

class Setup extends sfConsoleCommand
{
    const PG_TRGM_GIST  = 'gist';
    const PG_TRGM_GIN   = 'gin';

    // {{{ properties

    /**
     * @var array
     */
    private $config;

    /**
     * @var bool
     */
    private $dryRun;

    /**
     * @var string
     */
    private $pgTrgm;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $conn;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var array
     */
    private $tableNames = array(
        'error'  => null,
        'ignore' => null,
        'image'  => null,
    );

    /**
     * @var string
     */
    private $serialPriamryKey;

    /**
     * @var string
     */
    private $tableExtraDefs;

    /**
     * @var string
     */
    private $findTableStatement;

    /**
     * @var string
     */
    private $findIndexFormat;

    // }}}

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
        ->setName('setup')
        ->setDescription('Setups ImageCache2 environment')
        ->setDefinition(array(
            new InputOption('check-only', null, InputOption::VALUE_NONE, 'Don\'t execute anything'),
            new InputOption('pg-trgm', null, InputOption::VALUE_REQUIRED, 'Enable gist or gin 3-gram index'),
        ));
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->config = ic2_loadconfig();
        $this->dryRun = (bool)$input->getOption('check-only');
        $this->pgTrgm = $input->getOption('pg-trgm');
        $this->output = $output;

        if ($this->checkConfiguration()) {
            $result = $this->connect();
            if ($result) {
                $this->info('Database: OK');
                $this->serialPriamryKey = $result[0];
                $this->tableExtraDefs = $result[1];
                $this->createTables();
                $this->createIndexes();
            }
        }
    }

    /**
     * @return bool
     */
    private function checkConfiguration()
    {
        $result = true;

        $enabled  = $GLOBALS['_conf']['expack.ic2.enabled'];
        $database = $this->config['General']['database'];
        $driver   = $this->config['General']['driver'];

        $this->comment('enabled='  . var_export($enabled,  true));
        $this->comment('database=' . var_export($database, true));
        $this->comment('driver='   . var_export($driver,   true));

        if (!$enabled) {
            $this->error("\$_conf['expack.ic2.enabled'] is not enabled in conf/conf_admin_ex.inc.php.");
            $result = false;
        }

        if (!$database) {
            $this->error("\$_conf['expack.ic2.general.database'] is not set in conf/conf_ic2.inc.php.");
            $result = false;
        }

        $driver = strtolower($driver);
        switch ($driver) {
            case 'imagemagick6':
            case 'imagemagick':
                if (!ic2_findexec('convert', $this->config['General']['magick'])) {
                    $this->error("Command 'convert' is not found");
                    $result = false;
                } else {
                    $this->info('Image Driver: OK');
                }
                break;
            case 'gd':
            case 'imagick':
            case 'imlib2':
                if (!extension_loaded($driver)) {
                    $this->error("Extension {$driver} is not loaded");
                    $result = false;
                } else {
                    $this->info('Image Driver: OK');
                }
                break;
            default:
                $this->error('Unknow image driver.');
                $result = false;
        }

        foreach (glob(P2_CONFIG_DIR . '/ic2/ImageCache2.Entity.*.dcm.yml') as $yaml) {
            if (preg_match('/^(ImageCache2\\.Entity\\.([A-Z][A-Za-z0-9]*))\\./', basename($yaml), $matches)) {
                $className = str_replace('.', '\\', $matches[1]);
                $entityConfig = Yaml::parse($yaml);
                if (isset($entityConfig[$className]['table'])) {
                    $this->tableNames[strtolower($matches[2])] = $entityConfig[$className]['table'];
                }
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    private function connect()
    {
        $ini = ic2_loadconfig();

        if (strcasecmp($ini['General']['database']['driver'], 'pdo_sqlite') === 0) {
            $path = $ini['General']['database']['path'];
            if (!file_exists($path)) {
                if (touch($path)) {
                    chmod($path, 0666);
                }
            }
        }

        $this->conn = ic2_entitymanager()->getConnection();

        return $this->postConnect();
    }

    // {{{ post connect methods

    private function postConnect()
    {
        $result = null;

        switch ($this->conn->getDriver()->getName()) {
            case 'mysqli':
            case 'pdo_mysql':
                $result = $this->postConnectMysql();
                break;
            case 'pdo_pgsql':
                $result = $this->postConnectPgsql();
                break;
            case 'pdo_sqlite':
                $result = $this->postConnectSqlite();
                break;
        }

        return $result;
    }

    private function postConnectMysql()
    {
        $serialPriamryKey = 'INTEGER PRIMARY KEY AUTO_INCREMENT';
        $tableExtraDefs = ' ENGINE=InnoDB DEFAULT CHARACTER SET utf8';

        try {
            $this->findTableStatement = 'SHOW TABLES LIKE ?';
        } catch (DBALException $e) {
            $this->error($e->getMessage());
            return null;
        }

        $this->findIndexFormat = 'SHOW INDEX FROM %s WHERE Key_name LIKE ?';

        return array($serialPriamryKey, $tableExtraDefs);
    }

    private function postConnectPgsql()
    {
        $serialPriamryKey = 'SERIAL PRIMARY KEY';
        $tableExtraDefs = '';

        try {
            $this->findTableStatement = "SELECT relname FROM pg_class WHERE relkind = 'r' AND relname = ?";
        } catch (DBALException $e) {
            $this->error($e->getMessage());
            return null;
        }

        $this->findIndexFormat = "SELECT relname FROM pg_class WHERE relkind = 'i' AND relname = ?";

        return array($serialPriamryKey, $tableExtraDefs);
    }

    private function postConnectSqlite()
    {
        $serialPriamryKey = 'INTEGER PRIMARY KEY';
        $tableExtraDefs = '';

        try {
            $this->findTableStatement = "SELECT name FROM sqlite_master WHERE type = 'table' AND name= ?";
        } catch (DBALException $e) {
            $this->error($e->getMessage());
            return null;
        }

        $this->findIndexFormat = "SELECT name FROM sqlite_master WHERE type = 'index' AND name= ?";

        return array($serialPriamryKey, $tableExtraDefs);
    }

    // }}}
    // {{{ methods to create table

    private function createTables()
    {
        $imagesTable = $this->tableNames['image'];
        $errorLogTable = $this->tableNames['error'];
        $blackListTable = $this->tableNames['ignore'];

        if ($this->findTable($imagesTable)) {
            $this->info("Table '{$imagesTable}' already exists");
        } else {
            $this->createImagesTable($imagesTable);
        }

        if ($this->findTable($errorLogTable)) {
            $this->info("Table '{$errorLogTable}' already exists");
        } else {
            $this->createErrorLogTable($errorLogTable);
        }

        if ($this->findTable($blackListTable)) {
            $this->info("Table '{$blackListTable}' already exists");
        } else {
            $this->createBlackListTable($blackListTable);
        }
    }

    private function findTable($tableName)
    {
        return $this->conn->fetchColumn($this->findTableStatement, array($tableName)) !== false;
    }

    private function doCreateTable($tableName, $sql)
    {
        if ($this->dryRun) {
            $this->comment($sql);
            return true;
        }

        $this->conn->exec($sql);
        $this->info("Table '{$tableName}' created");

        return true;
    }

    private function createImagesTable($tableName)
    {
        $quotedTableName = $this->conn->quoteIdentifier($tableName);
        $sql = <<<SQL
CREATE TABLE {$quotedTableName} (
    id     {$this->serialPriamryKey},
    uri    VARCHAR (255),
    host   VARCHAR (255),
    name   VARCHAR (255),
    size   INTEGER NOT NULL,
    md5    CHAR (32) NOT NULL,
    width  SMALLINT NOT NULL,
    height SMALLINT NOT NULL,
    mime   VARCHAR (50) NOT NULL,
    time   INTEGER NOT NULL,
    rank   SMALLINT NOT NULL DEFAULT 0,
    memo   TEXT
){$this->tableExtraDefs};
SQL;
        return $this->doCreateTable($tableName, $sql);
    }

    private function createErrorLogTable($tableName)
    {
        $quotedTableName = $this->conn->quoteIdentifier($tableName);
        $sql = <<<SQL
CREATE TABLE {$quotedTableName} (
    uri     VARCHAR (255),
    errcode VARCHAR(64) NOT NULL,
    errmsg  TEXT,
    occured INTEGER NOT NULL
){$this->tableExtraDefs};
SQL;
        return $this->doCreateTable($tableName, $sql);
    }

    private function createBlackListTable($tableName)
    {
        $quotedTableName = $this->conn->quoteIdentifier($tableName);
        $sql = <<<SQL
CREATE TABLE {$quotedTableName} (
    id     {$this->serialPriamryKey},
    uri    VARCHAR (255),
    size   INTEGER NOT NULL,
    md5    CHAR (32) NOT NULL,
    type   SMALLINT NOT NULL DEFAULT 0
){$this->tableExtraDefs};
SQL;
        return $this->doCreateTable($tableName, $sql);
    }

    // }}}
    // {{{ methods to create index

    private function createIndexes()
    {
        $imagesTable = $this->tableNames['image'];
        $errorLogTable = $this->tableNames['error'];
        $blackListTable = $this->tableNames['ignore'];

        $indexes = array(
            $imagesTable => array(
                '_uri' => array('uri'),
                '_time' => array('time'),
                '_unique' => array('size', 'md5', 'mime'),
            ),
            $errorLogTable => array(
                '_uri' => array('uri'),
            ),
            $blackListTable => array(
                '_uri' => array('uri'),
                '_unique' => array('size', 'md5'),
            ),
        );

        foreach ($indexes as $tableName => $indexList) {
            foreach ($indexList as $indexNameSuffix => $fieldNames) {
                $indexName = 'idx_' . $tableName . $indexNameSuffix;
                if ($this->findIndex($indexName, $tableName)) {
                    $this->info("Index '{$indexName}' already exists");
                } else {
                    $this->doCreateIndex($indexName, $tableName, $fieldNames);
                }
            }
        }

        if ($this->conn->getDriver()->getName() === 'pdo_pgsql') {
            $pgTrgm = $this->pgTrgm;
            if ($pgTrgm === self::PG_TRGM_GIST ||
                $pgTrgm === self::PG_TRGM_GIN) {
                $indexName = 'idx_memo_tgrm';
                if ($this->findIndex($indexName, $imagesTable)) {
                    $this->info("Index '{$indexName}' already exists");
                } else {
                    $this->doCreatePgTrgmIndex($pgTrgm, $indexName,
                                               $imagesTable, 'memo');
                }
            }
        }
    }

    private function doCreateIndex($indexName, $tableName, array $fieldNames)
    {
        $callback = array($this->conn, 'quoteIdentifier');
        $sql = sprintf('CREATE INDEX %s ON %s (%s);',
                       $this->conn->quoteIdentifier($indexName),
                       $this->conn->quoteIdentifier($tableName),
                       implode(', ', array_map($callback, $fieldNames)));

        if ($this->dryRun) {
            $this->comment($sql);
            return true;
        }

        $this->conn->exec($sql);
        $this->info("Index '{$indexName}' created");

        return true;
    }

    private function doCreatePgTrgmIndex($indexType, $indexName, $tableName, $fieldName)
    {
        $sql = sprintf('CREATE INDEX %2$s ON %3$s USING %1$s (%4$s %1$s_trgm_ops);',
                       $indexType,
                       $this->conn->quoteIdentifier($indexName),
                       $this->conn->quoteIdentifier($tableName),
                       $this->conn->quoteIdentifier($fieldName));

        if ($this->dryRun) {
            $this->comment($sql);
            return true;
        }

        $this->conn->exec($sql);
        $this->info("{$indexType} Index '{$indexName}' created");

        return true;
    }

    private function findIndex($indexName, $tableName)
    {
        $sql = sprintf($this->findIndexFormat,
                       $this->conn->quoteIdentifier($tableName));

        return $this->conn->fetchColumn($sql, array($indexName)) !== false;
    }

    // }}}
    // {{{ console output methods

    private function info($message)
    {
        $this->output->writeln("<info>{$message}</info>");
    }

    private function comment($message)
    {
        $this->output->writeln("<comment>{$message}</comment>");
    }

    private function error($message)
    {
        if ($this->dryRun) {
            $this->output->writeln("<error>{$message}</error>");
        } else {
            throw new \Exception($message);
        }
    }

    // }}}
}
