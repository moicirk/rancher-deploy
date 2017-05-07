<?php
namespace Rancher;

use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Igor Murujev <imurujev@gmail.com>
 */
class UpgradeCommand extends ServiceCommand
{
    protected $batch;
    protected $interval;
    protected $config;

    protected function configure()
    {
        parent::configure();

        $this->setName('service:upgrade')
            ->setDescription('Rancher service upgrade command')
            ->setHelp('Upgrades rancher service')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Launch config')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Batch size', 1)
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Interval in milliseconds', 20000)
        ;
    }

    protected function getRequiredOptions()
    {
        $required = parent::getRequiredOptions();
        $required[] = 'config';
        return $required;
    }

    protected function runCommand()
    {
        $this->msg('Starting service upgrade');
        $launchConfig = $this->getLaunchConfig();

        try {
            $this->callServiceAction('upgrade', $launchConfig);
        }
        catch (ClientException $e) {
            if ($e->getCode() == 422) {
                throw new \Exception('Deploy already in process');
            }
        }

        $this->completeUpgrade();
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