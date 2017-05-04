<?php
namespace Rancher;

use Mauchede\RancherApi\Resource\Container;
use Mauchede\RancherApi\Resource\Project;
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

    protected function configure()
    {
        parent::configure();

        $this->setName('service')
            ->setDescription('Rancher service upgrade command')
            ->setHelp('Upgrades rancher service')
            ->addOption('stack-name', null, InputOption::VALUE_REQUIRED, 'Stack name')
            ->addOption('service-name', null, InputOption::VALUE_REQUIRED, 'Service to upgrade')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->parseOptions($input);
        $client = $this->getClient();

        /** @var Project $project */
        $project = $client->get($this->url, Project::class);
        $services = $project->getServices();

        /** @var Container $container */
        foreach ($services as $service) {
            $output->writeln('yo');
            $output->writeln($service->getName());
        }
    }

    protected function options(): array
    {
        return array_merge(parent::options(), [
            'stack-name' => 'stackName',
            'service-name' => 'serviceName'
        ]);
    }

    protected function requiredOptions(): array
    {
        return parent::requiredOptions() + ['stack-name', 'service-name'];
    }
}