<?php
namespace Rancher;

use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Igor Murujev <imurujev@gmail.com>
 */
abstract class ServiceCommand extends AbstractCommand
{
    protected $stackName;
    protected $serviceName;

    private $service;

    protected function configure()
    {
        parent::configure();

        $this
            ->addOption('stack-name', null, InputOption::VALUE_REQUIRED, 'Stack name')
            ->addOption('service-name', null, InputOption::VALUE_REQUIRED, 'Service to upgrade')
        ;
    }

    protected function getRequiredOptions()
    {
        $required = parent::getRequiredOptions();
        $required[] = 'stackName';
        $required[] = 'serviceName';
        return $required;
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
}