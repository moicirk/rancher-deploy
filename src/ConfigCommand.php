<?php
namespace Rancher;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Igor Murujev <imurujev@gmail.com>
 */
class ConfigCommand extends AbstractCommand
{
    protected function configure()
    {
        parent::configure();

        $this->setName('config')
            ->setDescription('Rancher config')
            ->setHelp('Stores rancher config')
        ;
    }

    protected function runCommand()
    {
        $this->msg("Saving config in {$this->configFile}");

        $config = [
            'url' => $this->url,
            'accessKey' => $this->accessKey,
            'secretKey' => $this->secretKey
        ];

        file_put_contents($this->configFile, \json_encode($config));
        $this->msg('Config file generated');
    }
}