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
    protected $image;

    protected function configure()
    {
        parent::configure();

        $this->setName('service:upgrade')
            ->setDescription('Rancher service upgrade command')
            ->setHelp('Upgrades rancher service')
            ->addOption('image', null, InputOption::VALUE_REQUIRED, 'Image to launch')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Batch size', 1)
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Interval in milliseconds', 20000)
        ;
    }

    protected function getRequiredOptions()
    {
        $required = parent::getRequiredOptions();
        $required[] = 'image';
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
        $failuresCount = 0;
        $overallCount = 0;
        $url = "services/{$service->id}";
        while (true) {
            $response = $this->getClient()->get($url);
            $response = \GuzzleHttp\json_decode($response->getBody());

            $health = $response->healthState;

            $this->msg("Service health state {$health}");
            $this->msg("Transitioning: {$response->transitioning}");
            if ($response->transitioning == 'no') {
                $this->msg('No longer transitioning');
                return $health;
            }

            if ($health == 'unhealthy') {
                ++$failuresCount;
                if ($failuresCount === 7) {
                    $this->msg('Unhealthy upgrade. Cancelling');
                    $this->callServiceAction('cancelupgrade', ['action' => 'cancelupgrade']);
                    return $health;
                }
            }

            ++$overallCount;
            if ($overallCount > 20) {
                $this->msg("Upgrading is to long, cancelling");
                $this->callServiceAction('cancelupgrade', ['action' => 'cancelupgrade']);
                return $health;
            }

            sleep(3);
        }
    }

    protected function getLaunchConfig()
    {
        $launchConfig = (array)$this->getService()->launchConfig;
        $launchConfig['imageUuid'] = 'docker:' . $this->image;

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