<?php

namespace expack\Console\Command;

use Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class Archive extends Command
{
    const BUILD_DIR = 'build';
    const ARCHIVE_NAME_PREFIX = 'rep2ex-';

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
        ->setName('archive')
        ->setDescription('Makes an archive')
        ->setDefinition(array(
            new InputOption('branch', null,  InputOption::VALUE_REQUIRED, 'Specify branch, tag or commit'),
            new InputOption('clear', null,  InputOption::VALUE_NONE, 'Remove targets befor export'),
        ));
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbose = (bool)$input->getOption('verbose');

        if ((bool)$input->getOption('clear')) {
            if (!$this->clear($output, $verbose)) {
                return 1;
            }
        }

        $branch = $input->getOption('branch');
        if ($branch) {
            if (!$this->checkout($branch, $output, $verbose)) {
                return 1;
            }
        }

        if (!$this->exportRep2($output, $verbose)) {
            return 1;
        }

        if (!$this->copyDirectory('vendor', $output, $verbose)) {
            return 1;
        }

        if (!$this->archive($output, $verbose)) {
            return 1;
        }

        $output->writeln('<comment>Success</comment>');

        return 0;
    }

    /**
     * Clears the targets
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function clear(OutputInterface $output, $verbose = false)
    {
        $prefix = $this->getExportPrefix();
        $command = 'rm -fr' . ($verbose ? 'v' : '')
                 . ' ' . escapeshellarg($prefix)
                 . ' ' . escapeshellarg($prefix . '.') . '*';

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Checkouts the branch
     *
     * @param string $branch
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function checkout($branch, OutputInterface $output, $verbose = false)
    {
        $command = 'git checkout ' . ($verbose ? '' : ' --quiet')
                 . ' ' . escapeshellarg($branch);

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Export rep2
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function exportRep2(OutputInterface $output, $verbose = false)
    {
        $prefix = $this->getExportPrefix() . '/';
        $command = 'git checkout-index -a -f' . ($verbose ? '' : ' --quiet')
                 . ' --prefix=' . escapeshellarg($prefix);

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Copies the directory
     *
     * @param string $directory
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function copyDirectory($directory, OutputInterface $output, $verbose = false)
    {
        $target = $this->getExportPrefix() . '/' . $directory;
        $command = 'cp -R' . ($verbose ? 'v' : '')
                 . ' ' . escapeshellarg($directory)
                 . ' ' . escapeshellarg($target);

        if ($this->execCommand($command, $output) !== 0) {
            return false;
        }

        if (is_executable('/usr/bin/xattr')) {
            $command = '/usr/bin/xattr -cr ' . escapeshellarg($target);

            return $this->execCommand($command, $output) === 0;
        }

        return true;
    }

    /**
     * Makes the archives
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function archive(OutputInterface $output, $verbose = false)
    {
        if ($verbose) {
            $output->writeln('<comment>cd ' . self::BUILD_DIR . '</comment>');
        }
        if (!chdir(self::BUILD_DIR)) {
            return false;
        }

        $success = true;
        foreach (array('archive7zip', 'archiveTar', 'archiveZip') as $method) {
            if (!$this->$method($output, $verbose)) {
                $success = false;
                break;
            }
        }

        if ($verbose) {
            $output->writeln('<comment>cd ..</comment>');
        }
        chdir('..');

        return $success;
    }

    /**
     * Makes the tar archive
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function archiveTar(OutputInterface $output, $verbose = false)
    {
        $tar = 'tar';
        if (is_executable('/usr/bin/gnutar')) {
            $tar = '/usr/bin/gnutar';
        }

        $name = $this->getArchiveName();
        $command = implode(' ', array(
            $tar,
            'cf' . ($verbose ? 'v' : ''),
            escapeshellarg($name . '.tar'),
            escapeshellarg($name),
        ));

        if ($this->execCommand($command, $output) !== 0) {
            return false;
        }

        $command = 'gzip -9 ' . escapeshellarg($name . '.tar');

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Makes the zip archive
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function archiveZip(OutputInterface $output, $verbose = false)
    {
        $options = '-9';
        $xattr = exec('zip --help | grep -F -- -X', $code);
        if ($code && strpos($xattr, 'eXclude eXtra file attributes') !== false) {
            $options .= 'X';
        }
        if (!$verbose) {
            $options .= 'q';
        }
        $options .= 'r';

        $name = $this->getArchiveName();
        $command = implode(' ', array(
            'zip',
            $options,
            escapeshellarg($name . '.zip'),
            escapeshellarg($name),
        ));

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Makes the 7-zip archive
     *
     * @param OutputInterface $output
     * @param bool $verbose
     *
     * @return bool
     */
    private function archive7zip(OutputInterface $output, $verbose = false)
    {
        $name = $this->getArchiveName();
        $command = implode(' ', array(
            '7za',
            'a',
            '-mx=9',
            escapeshellarg($name . '.7z'),
            escapeshellarg($name),
        ));

        if (!$verbose) {
            $command .= ' > /dev/null';
        }

        return $this->execCommand($command, $output) === 0;
    }

    /**
     * Gets the export prefix
     *
     * @param void
     *
     * @return string
     */
    private function getExportPrefix()
    {
        return self::BUILD_DIR . '/' . $this->getArchiveName();
    }

    /**
     * Gets the archive name
     *
     * @param void
     *
     * @return string
     */
    private function getArchiveName()
    {
        return self::ARCHIVE_NAME_PREFIX
            . preg_replace('/\\D/', '', $GLOBALS['_conf']['p2version']);
    }
}
