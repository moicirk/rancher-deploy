<?php
namespace Rancher;

use GuzzleHttp\Client as HttpClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Igor Murujev <imurujev@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    protected const EXIT_CODE_SUCCESS = 0;
    protected const EXIT_CODE_ERROR = 1;

    protected $url;
    protected $accessKey;
    protected $secretKey;
    protected $timeout;
    protected $debug;

    private $client;

    protected function configure()
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Rancher API url')
            ->addOption('access-key', null, InputOption::VALUE_REQUIRED, 'Access key')
            ->addOption('secret-key', null, InputOption::VALUE_REQUIRED, 'Secret key')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Client timeout', 60)
            ->addOption('debug', null, InputOption::VALUE_REQUIRED, 'Verbose', 0)
        ;
    }

    protected function parseOptions(InputInterface $input): void
    {
        $options = [
            'url' => 'url',
            'access-key' => 'accessKey',
            'secret-key' => 'secretKey',
            'timeout' => 'timeout',
            'debug' => 'debug'
        ];

        $required = ['url', 'access-key', 'secret-key'];
        foreach ($options as $optionName => $optionProperty) {
            $this->{$optionProperty} = $input->getOption($optionName);
            if (!$this->{$optionProperty} && in_array($optionName, $required)) {
                throw new \Exception("Option {$optionName} is required");
            }
        }
    }

    /**
     * @return HttpClient
     */
    protected function getClient(): HttpClient
    {
        if ($this->client) {
            return $this->client;
        }

        $this->client = new HttpClient([
            'base_uri' => $this->url,
            'auth' => [$this->accessKey, $this->secretKey],
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 60,
            'debug' => $this->debug
        ]);

        return $this->client;
    }

    protected function getUrl(string $route): string
    {
        return rtrim($this->url, '/') . '/' . ltrim($route, '/');
    }

    protected function options(): array
    {
        return [
            'url' => 'url',
            'access-key' => 'accessKey',
            'secret-key' => 'secretKey',
            'timeout' => 'timeout'
        ];
    }

    protected function requiredOptions(): array
    {
        return ['url', 'access-key', 'secret-key'];
    }
}