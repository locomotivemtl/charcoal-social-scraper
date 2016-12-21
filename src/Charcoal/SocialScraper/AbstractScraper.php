<?php

namespace Charcoal\SocialScraper;

use DateTime;
use DateTimeInterface;
use OutOfBoundsException;
use RuntimeException;

// Module `charcoal-factory` dependencies
use Charcoal\Factory\FactoryInterface;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Exception\MissingOptionsException;
use Charcoal\SocialScraper\Object\ScrapeRecord;

/**
 * Generic scraping class intended for connection to a social network API
 * through a client and saving results as Charcoal Objects.
 */
abstract class AbstractScraper
{
    /**
     * Store the model factory instance.
     *
     * @var FactoryInterface
     */
    protected $modelFactory;

    /**
     * Store the API client.
     *
     * @var object
     */
    protected $client;

    /**
     * Results of a scrape.
     *
     * @var array
     */
    protected $results;

    /**
     * Custom configuration.
     *
     * @var array
     */
    private $config = [];

    /**
     * Default configuration.
     *
     * @var array
     */
    private $defaultConfig = [
        'record'         => true,
        'recordExpires'  => '1 hour',
        'recordOptions'  => [
            'network'    => '',
            'repository' => '',
            'method'     => '',
            'filters'    => ''
        ]
    ];

    /**
     * Immutable configuration.
     *
     * @var array
     */
    protected $immutableConfig = [];

    /**
     * @param array $data The constructor options.
     * @return void
     */
    public function __construct(array $data)
    {
        if (isset($data['config'])) {
            $this->setConfig($data['config']);
        }

        if (isset($data['client'])) {
            $this->setClient($data['client']);
        }

        $this->setModelFactory($data['model_factory']);
    }

    /**
     * Set an object model factory.
     *
     * @param FactoryInterface $factory The model factory, to create objects.
     * @return self
     */
    protected function setModelFactory(FactoryInterface $factory)
    {
        $this->modelFactory = $factory;

        return $this;
    }

    /**
     * Retrieve the object model factory.
     *
     * @throws RuntimeException If the model factory was not previously set.
     * @return FactoryInterface
     */
    protected function modelFactory()
    {
        if (!isset($this->modelFactory)) {
            throw new RuntimeException(
                'Can not access model factory, the dependency has not been set.'
            );
        }

        return $this->modelFactory;
    }

    /**
     * Attempt to get the latest ScrapeRecord according to specific properties
     *
     * @throws MissingOptionsException If a required option is missing.
     * @return ModelInterface A ScrapeRecord instance.
     */
    public function fetchRecentScrapeRecord()
    {
        $config = $this->config();

        /** @var array $recordOptions ScrapeRecord Options */
        $recordOptions = $config['recordOptions'];
        ksort($recordOptions);

        // Check whether any required option is missing
        $recordOptions = array_filter($recordOptions);
        $diff = array_diff_key($recordOptions, $this->defaultConfig('recordOptions'));

        if (count($diff) > 0) {
            if (count($diff) > 1) {
                $message = 'The required options "%s" are missing.';
            } else {
                $message = 'The required option "%s" is missing.';
            }
            throw new MissingOptionsException(sprintf(
                'Bad configuration. '.$message,
                implode('", "', array_keys($diff))
            ));
        }

        // Create a proto model to generate the ident
        $record = $this->modelFactory()->create(ScrapeRecord::class);
        $record->setData([
            'network'    => $recordOptions['network'],
            'repository' => $recordOptions['repository'],
            'method'     => $recordOptions['method'],
            'filters'    => $recordOptions['filters']
        ]);
        $record->loadLatestRecord($this->recordExpiration());

        return $record;
    }

    /**
     * Retrieve results.
     *
     * @return ModelInterface[]|array|null
     */
    public function results()
    {
        return $this->results;
    }

    /**
     * Set results.
     *
     * @param  ModelInterface[]|array $results One or more objects.
     * @return self
     */
    public function setResults($results)
    {
        $this->results = $results;

        return $this;
    }

    /**
     * Retrieve the record expiration date/time.
     *
     * @return DateTimeInterface|null
     */
    public function recordExpiration()
    {
        return new DateTime('now - '.$this->config('recordExpires'));
    }

    /**
     * Retrieve the web scraper configset or a given key's value.
     *
     * @param  string|null $key     Optional data key to retrieve from the configset.
     * @param  mixed|null  $default The default value to return if data key does not exist.
     * @return array
     */
    public function config($key = null, $default = null)
    {
        if ($key) {
            if (isset($this->config[$key])) {
                return $this->config[$key];
            } else {
                if (!is_string($default) && is_callable($default)) {
                    return $default();
                } else {
                    return $default;
                }
            }
        }

        return $this->config;
    }

    /**
     * Replace the web scraper configset with the given parameters.
     *
     * @param  array $config New config values.
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = array_replace_recursive(
            $this->defaultConfig(),
            $config,
            $this->immutableConfig()
        );

        return $this;
    }

    /**
     * Merge given parameters into the web scraper configset.
     *
     * @param  array $config New config values.
     * @return self
     */
    public function mergeConfig(array $config)
    {
        $this->config = array_replace_recursive(
            $this->defaultConfig(),
            $this->config,
            $config,
            $this->immutableConfig()
        );

        return $this;
    }

    /**
     * Retrieve the default web scraper configuration.
     *
     * @param  string|null $key Optional data key to retrieve from the configset.
     * @throws OutOfBoundsException If the requested setting does not exist.
     * @return array
     */
    protected function defaultConfig($key = null)
    {
        if ($key) {
            if (isset($this->defaultConfig[$key])) {
                return $this->defaultConfig[$key];
            } else {
                throw new OutOfBoundsException(sprintf(
                    'The setting "%s" does not exist.',
                    $key
                ));
            }
        }

        return $this->defaultConfig;
    }

    /**
     * Retrieve the immutable options of the web scraper.
     *
     * @param  string|null $key Optional data key to retrieve from the configset.
     * @throws OutOfBoundsException If the requested setting does not exist.
     * @return array
     */
    protected function immutableConfig($key = null)
    {
        if ($key) {
            if (isset($this->immutableConfig[$key])) {
                return $this->immutableConfig[$key];
            } else {
                throw new OutOfBoundsException(sprintf(
                    'The setting "%s" does not exist.',
                    $key
                ));
            }
        }

        return $this->immutableConfig;
    }
}
