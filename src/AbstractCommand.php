<?php
namespace Rancher;

use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Igor Murujev <imurujev@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    protected $url;
    protected $accessKey;
    protected $secretKey;
    protected $timeout;
    protected $debug;

    /** @var OutputInterface */
    protected $output;

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->parseOptions($input);
        if ($this->before()) {
            $this->runCommand();
        }
    }

    protected function before()
    {
        return true;
    }

    abstract protected function runCommand();

    protected function msg($message)
    {
        $this->output->writeln($message);
    }

    protected function parseOptions(InputInterface $input)
    {
        foreach ($input->getOptions() as $option => $value) {
            $option = str_replace('-', '', ucwords($option, '-'));
            $option = strtolower($option[0]) . substr($option, 1);

            if (property_exists($this, $option)) {
                $this->{$option} = $value;
            }
        }
    }

    /**
     * @return HttpClient
     */
    protected function getClient()
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

    protected function getCollection($route)
    {
        return $this->parseResponse($this->getClient()->get($route));
    }

    protected function getResource($route)
    {
        $json = $this->parseResponse($this->getClient()->get($route));
        if (empty($json)) {
            return null;
        }
        return $json[0];
    }

    protected function parseResponse(ResponseInterface $response)
    {
        $json = \GuzzleHttp\json_decode($response->getBody());
        return $json->data;
    }
}