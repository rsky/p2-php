<?php

namespace expack\Console\Command;

use Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class Update extends Command
{
    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
        ->setName('update')
        ->setDescription('Updates rep2 expack')
        ->setDefinition(array(
            new InputOption('no-rep2',  null, InputOption::VALUE_NONE, 'Don\'t update rep2'),
            new InputOption('alldeps',  null, InputOption::VALUE_NONE, 'Update all depenencies'),
            new InputOption('composer', null, InputOption::VALUE_NONE, 'Update composer.phar'),
            new InputOption('pear',     null, InputOption::VALUE_NONE, 'Update PEAR libraries'),
            new InputOption('vendor',   null, InputOption::VALUE_NONE, 'Update vendor libraries'),
        ));
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbose = (bool)$input->getOption('verbose');

        if (!$input->getOption('no-rep2')) {
            if (!$this->updateSelf($output, $verbose)) {
                return 1;
            }
        }

        $updateAllDeps = (bool)$input->getOption('alldeps');
        foreach (array('pear', 'composer', 'vendor') as $dep) {
            if ($updateAllDeps || $input->getOption($dep)) {
                $method = 'update' . ucfirst($dep);
                if (!$this->$method($output, $verbose)) {
                    return 1;
                }
            }
        }

        return 0;
    }

    /**
     * Update rep2
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function updateSelf(OutputInterface $output, $verbose = false)
    {
        $command = 'git pull' . ($verbose ? '' : ' --quiet');

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Update PEAR libraries
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function updatePear(OutputInterface $output, $verbose = false)
    {
        $quiet = ($verbose ? '' : ' --quiet');
        $command = 'git submodule' . $quiet . ' foreach '
                 .  escapeshellarg('git fetch' . $quiet . ' origin');
        if ($this->execCommand($command, $output) !== 0) {
            return false;
        }

        $command = 'git submodule' . $quiet . ' update';

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Update composer.phar
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function updateComposer(OutputInterface $output, $verbose = false)
    {
        $command = escapeshellarg($this->getPhpBin())
                 . ' -d detect_unicode=0 composer.phar selfupdate';

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Update vendor libraries
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function updateVendor(OutputInterface $output, $verbose = false)
    {
        $command = escapeshellarg($this->getPhpBin())
                 . ' -d detect_unicode=0 composer.phar update';

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Get PHP executable's path
     *
     * @param void
     *
     * @return string
     */
    private function getPhpBin()
    {
        if (defined('PHP_BINARY')) {
            return PHP_BINARY;
        }

        return PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
    }
}
