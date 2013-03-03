<?php

namespace expack\Console\Command;

use Symfony\Component\Console\Command\Command as sfConsoleCommand;
use Dropbox,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class DropboxAuth extends sfConsoleCommand
{
    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
        ->setName('dropbox-auth')
        ->setDescription('Autorize Dropbox app')
        ->setDefinition(array(
            new InputOption('key', null,  InputOption::VALUE_REQUIRED, 'Dropbox app key'),
            new InputOption('secret', null,  InputOption::VALUE_REQUIRED, 'Dropbox app secret'),
            //new InputOption('sandbox', null, InputOption::VALUE_NONE, 'Authorize as a sandbox app')
        ));
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $appAuthJsonFile = $GLOBALS['_conf']['dropbox_auth_json'];
        $clientIdentifier = $GLOBALS['_conf']['p2name'];
        $callbackUrl = null;

        $key = $input->getOption('key');
        $secret = $input->getOption('secret');
        /*if ($input->getOption('sandbox')) {
            $accessType = Dropbox\AccessType::AppFolder();
            $accessTypeName = 'AppFolder';
        } else {*/
            $accessType = Dropbox\AccessType::FullDropbox();
            $accessTypeName = 'FullDropbox';
        //}

        $appInfo = new Dropbox\AppInfo($key, $secret, $accessType);
        $dbxConfig = new Dropbox\Config($appInfo, $clientIdentifier);
        $webAuth = new Dropbox\WebAuth($dbxConfig);

        list($requestToken, $authorizeUrl) = $webAuth->start($callbackUrl);
        $output->writeln("<comment>1. Go to the following URL:</comment>");
        $output->writeln("<info>{$authorizeUrl}</info>");
        $output->writeln("<comment>2. Click \"Allow\" (you might have to log in first).</comment>");
        $output->writeln("<comment>3. Hit ENTER to continue.</comment>");

        fgets(STDIN);

        list($accessToken, $dropboxUserId) = $webAuth->finish($requestToken);
        $serializedAccessToken = $accessToken->serialize();

        $output->writeln("<comment>Authorization complete.</comment>");
        $output->writeln("<comment>- User ID: {$dropboxUserId}</comment>");
        $output->writeln("<comment>- Serialized Access Token: {$serializedAccessToken}</comment>");

        $jsonOptions = 0;
        if (defined('JSON_PRETTY_PRINT')) {
            $jsonOptions |= \JSON_PRETTY_PRINT;
        }
        $json = json_encode(array(
            'app' => array(
                'key' => $key,
                'secret' => $secret,
                'access_type' => $accessTypeName,
            ),
            'access_token' => $serializedAccessToken,
        ), $jsonOptions) . PHP_EOL;

        if (file_put_contents($appAuthJsonFile, $json) !== false) {
            $output->writeln("<info>Saved authorization information to \"{$appAuthJsonFile}\".</info>");
        } else {
            $output->writeln("<error>Error saving to \"{$appAuthJsonFile}\".</error>");
            $output->writeln("<info>Dumping to stderr instead:</info>");
            $output->write($json, OutputInterface::OUTPUT_RAW);
        }
    }
}
