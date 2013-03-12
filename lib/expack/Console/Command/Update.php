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
            new InputOption('alldeps',  null, InputOption::VALUE_NONE, 'Update all depenencies (default)'),
            new InputOption('no-rep2',  null, InputOption::VALUE_NONE, 'Don\'t update rep2'),
            new InputOption('no-deps',  null, InputOption::VALUE_NONE, 'Don\'t update depenencies'),
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

        if ($input->getOption('no-deps')) {
            return 0;
        }

        if ($input->getOption('alldeps')) {
            $output->writeln('<notice>--alldeps is enabled by default and is deprecated.</notice>');
            $output->writeln('<notice>Then it will be removed in the future version of rep2ex.</notice>');
        }

        if (!$this->updateComposer($output, $verbose)) {
            return 1;
        }

        if (!$this->updateVendor($output, $verbose)) {
            return 1;
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
