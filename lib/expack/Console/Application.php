<?php

namespace expack\Console;

use Symfony\Component\Console\Application as sfConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use expack\Console\Command;

require_once __DIR__ . '/Command/Command.php';
require_once __DIR__ . '/Command/Archive.php';
require_once __DIR__ . '/Command/Check.php';
require_once __DIR__ . '/Command/Update.php';
require_once __DIR__ . '/Command/DropboxAuth.php';

class Application extends sfConsoleApplication
{
    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Application::__construct()
     */
    public function __construct()
    {
        parent::__construct('rep2-expack console script', '1.0');
        $this->addCommands(array(
            new Command\Archive(),
            new Command\Check(),
            new Command\Update(),
            new Command\DropboxAuth(),
        ));
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Application::doRun()
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $noticeStyle = new OutputFormatterStyle('black', 'yellow');
        $output->getFormatter()->setStyle('notice', $noticeStyle);

        return parent::doRun($input, $output);
    }
}
