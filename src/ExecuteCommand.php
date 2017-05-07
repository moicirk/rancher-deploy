<?php
namespace Rancher;

use Symfony\Component\Console\Input\InputOption;

/**
 * @author Igor Murujev <imurujev@gmail.com>
 */
class ExecuteCommand extends ServiceCommand
{
    protected $command;
    protected $lines;

    private $container;

    protected function configure()
    {
        parent::configure();

        $this->setName('service:execute')
            ->setDescription('Rancher service execute command')
            ->setHelp('Executes command on first found container in service')
            ->addOption('command', null, InputOption::VALUE_REQUIRED, 'Command to execute')
            ->addOption('lines', null, InputOption::VALUE_REQUIRED, 'Log lines amount', 50)
        ;
    }

    protected function getRequiredOptions()
    {
        $required = parent::getRequiredOptions();
        $required[] = 'command';
        return $required;
    }

    protected function runCommand()
    {
        $this->msg('Executing command');
        $container = $this->getContainer();

        $response = $this->getClient()->post("containers/{$container->id}/?action=execute", [
            'json' => [
                'attachStdin' => true,
	            'attachStdout' => true,
                'command' => ["bash", "-c", $this->command],
	            'tty' => true
            ]
        ]);

        $response = \GuzzleHttp\json_decode($response->getBody());
        $wsUrl = $response->url . '?token=' . $response->token;

        \Ratchet\Client\connect($wsUrl)->then(function($conn) {
            $conn->on('message', function($msg) use ($conn) {
                $this->msg(base64_decode($msg));
            });
        }, function ($e) {
            echo "Could not connect: {$e->getMessage()}\n";
        });
    }

    protected function getContainer()
    {
        if ($this->container) {
            return $this->container;
        }

        $service = $this->getService();

        $containers = $this->getCollection('containers');
        foreach ($containers as $cont) {
            if ($cont->serviceIds && in_array($service->id, $cont->serviceIds)) {
                $this->container = $cont;
                $this->msg("Container name {$cont->name} will be used");
                break;
            }
        }

        if ($this->container === null) {
            throw new \Exception("No active container found for service {$this->serviceName}");
        }

        return $this->container;
    }
}