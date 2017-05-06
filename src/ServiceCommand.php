<?php
namespace Rancher;

use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Igor Murujev <imurujev@gmail.com>
 */
class ServiceCommand extends AbstractCommand
{
    protected $stackName;
    protected $serviceName;
    protected $batch;
    protected $interval;
    protected $config;

    private $service;

    protected function configure()
    {
        parent::configure();

        $this->setName('service')
            ->setDescription('Rancher service upgrade command')
            ->setHelp('Upgrades rancher service')
            ->addOption('stack-name', null, InputOption::VALUE_REQUIRED, 'Stack name')
            ->addOption('service-name', null, InputOption::VALUE_REQUIRED, 'Service to upgrade')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Launch config')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Batch size', 1)
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Interval in milliseconds', 20000)
        ;
    }

    protected function before()
    {
        $required = ['url', 'accessKey', 'secretKey', 'stackName', 'serviceName', 'config'];
        foreach ($required as $prop) {
            if (!$this->{$prop}) {
                throw new \Exception("Option {$prop} is required");
            }
        }
        return true;
    }

    protected function runCommand()
    {
        $this->msg('Starting service upgrade');
        $launchConfig = $this->getLaunchConfig();

        try {
            $response = $this->callServiceAction('upgrade', $launchConfig);
        }
        catch (ClientException $e) {
            var_dump((string)$e->getResponse()->getBody());exit;
            if ($e->getCode() == 422) {
                throw new \Exception('Deploy already in process');
            }
        }

        $this->completeUpgrade();
    }

    /**
     * @return \stdClass
     * @throws \Exception
     */
    protected function getService()
    {
        if ($this->service) {
            return $this->service;
        }

        $stack = $this->getResource('stacks?name=' . $this->stackName);
        if (!$stack) {
            throw new \Exception("Stack named {$this->stackName} not found");
        }

        $service = $this->getResource("services?name={$this->serviceName}&stackId={$stack->id}");
        if (!$service) {
            throw new \Exception("Service named {$this->serviceName} not found in stack {$this->stackName}");
        }

        $this->service = $service;
        return $this->service;
    }


    protected function callServiceAction($action, array $data = [])
    {
        $serviceId = $this->getService()->id;
        return $this->getClient()->post("services/{$serviceId}/?action={$action}", [
            'json' => $data
        ]);
    }

    protected function completeUpgrade()
    {
        $health = $this->waitForServiceHealth();
        $this->msg("Got health: {$health}");
        $action = ($health == 'healthy' || $health == 'started-once') ? 'finishupgrade' : 'rollback';

        $this->msg("Requesting action {$action}");
        $this->callServiceAction($action, ['action' => $action]);

        if ($action == 'rollback') {
            throw new \Exception('Service was unhealthy, a rollback occurred');
        }
    }

    protected function waitForServiceHealth()
    {
        $service = $this->getService();
        $count = 0;
        $url = "services/{$service->id}";
        while (true) {
            $response = $this->parseResponse($this->getClient()->get($url));
            $health = $response->healthState;

            $this->msg("Service health state {$health}");
            if ($response->transitioning == 'no') {
                $this->msg('No longer transitioning');
                return $health;
            }

            if ($health == 'unhealthy') {
                ++$count;
                if ($count === 7) {
                    $this->msg('Unhealthy upgrade. Cancelling');
                    $this->callServiceAction('cancelupgrade', ['action' => 'cancelupgrade']);
                }
            }

            sleep(3);
        }
    }

    protected function getLaunchConfig()
    {
        if (!file_exists($this->config)) {
            throw new \Exception("File {$this->config} not found");
        }

        $launchConfig = (array)$this->getService()->launchConfig;
        $launchConfig = array_merge($launchConfig, require($this->config));

        return [
            'inServiceStrategy' => [
                'batchSize' => $this->batch,
                'intervalMillis' => $this->interval,
                'startFirst' => true,
                'launchConfig' => $launchConfig
            ]
        ];
    }
}