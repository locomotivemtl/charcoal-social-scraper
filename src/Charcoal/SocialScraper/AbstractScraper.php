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
use Charcoal\SocialScraper\Traits\ConfigurableTrait;

/**
 * Generic scraping class intended for connection to a social network API
 * through a client and saving results as Charcoal Objects.
 */
abstract class AbstractScraper
{
    use ConfigurableTrait;

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
     * Preset scrape requests.
     *
     * @var array
     */
    private $requests = [];

    /**
     * Default preset scrape request.
     *
     * @var string|null
     */
    private $defaultRequest = 'default';

    /**
     * The resolved settings.
     *
     * @var array
     */
    protected $resolvedConfig = [];

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $defaultConfig = [
        'record'         => true,
        'recordExpires'  => '1 hour',
        'updateRecord'   => false,
        'recordOptions'  => [
            'network'    => '',
            'repository' => '',
            'method'     => '',
            'filters'    => []
        ]
    ];

    /**
     * The immutable settings.
     *
     * @var array
     */
    protected $immutableConfig = [];

    /**
     * Map of aliases to data models.
     *
     * `[ 'model-alias' => 'Fully/Qualified/ClassName' ]`
     *
     * @var array
     */
    protected $modelMap;

    /**
     * Map of default data to apply to new models.
     *
     * `[ 'model-alias' => [ … ] ]`
     *
     * @var array
     */
    protected $modelData = [];

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

        if (isset($data['requests'])) {
            $this->setRequests($data['requests']);
        }

        if (isset($data['default_request'])) {
            $this->setDefaultRequest($data['default_request']);
        }

        if (isset($data['model_data'])) {
            $this->setModelData($data['model_data']);
        }

        if (isset($data['model_map'])) {
            $this->setModelMap($data['model_map']);
        } else {
            $this->modelMap();
        }

        $this->setModelFactory($data['model_factory']);
    }

    /**
     * Retrieve a human-friendly label or description of the scraper.
     *
     * @return string|null
     */
    abstract public function label();

    /**
     * @param  string|array|null $request Either a preset request key or a request config.
     *     If no $settings are supplied, the default preset request is used.
     * @param  array|null        $params  Custom scraping options.
     * @return ModelInterface[]|null
     */
    abstract public function scrape($request = null, array $params = null);

    /**
     * Parse the scrape request.
     *
     * @param  string|array|null $request Either a preset request key or a request config.
     *     If no $settings are supplied, the default preset request is used.
     * @param  array|null        $params  Custom scraping options.
     * @return array Returns the processed request parameters.
     */
    protected function parseScrapeRequest($request = null, array $params = null)
    {
        if ($request === null) {
            $request = $this->defaultRequest();
        } elseif (is_array($request)) {
            $params  = $request;
            $request = null;
        }

        if (is_string($request)) {
            if (is_array($params)) {
                $params = array_replace_recursive(
                    $this->request($request),
                    $params
                );
            } else {
                $params = $this->request($request);
            }
        }

        return $params;
    }

    /**
     * Create a scrape record.
     *
     * @throws MissingOptionsException If a required option is missing.
     * @return ModelInterface A ScrapeRecord instance.
     */
    public function createScrapeRecord()
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
        $record = $this->createModel('record');
        $record->setData([
            'network'    => $recordOptions['network'],
            'repository' => $recordOptions['repository'],
            'method'     => $recordOptions['method'],
            'filters'    => $recordOptions['filters']
        ]);

        return $record;
    }

    /**
     * Attempt to get the latest ScrapeRecord according to specific properties
     *
     * @return ModelInterface A ScrapeRecord instance.
     */
    public function fetchRecentScrapeRecord()
    {
        $record = $this->createScrapeRecord();
        $record->loadLatestRecord($this->recordExpiration());

        return $record;
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
     * Create an object model.
     *
     * Uses the {@see self::modelFactory()} and will lookup the {@see self::modelMap() scraper's class-map}.
     *
     * @param  string  $type     The "type" of object to create.
     * @param  boolean $withData Whether to merge the scraper's
     *     "extra" default data (TRUE) or not (FALSE).
     * @throws InvalidArgumentException If the type is not a string.
     * @return ModelInterface
     */
    protected function createModel($type, $withData = true)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException(
                'Can not resolve class ident: type must be a string'
            );
        }

        $alias = null;
        if (!empty($this->modelMap[$type])) {
            $alias = $type;
            $type  = $this->modelMap[$type];
        }

        $model = $this->modelFactory()->create($type);
        if ($withData) {
            if ($alias) {
                $data = $this->modelData($alias);
                $model->setData($data);
            }

            $data = $this->modelData($type);
            $model->setData($data);
        }

        return $model;
    }

    /**
     * Retrieve map of aliases to data models.
     *
     * @param  string|null $alias Optional model to retrieve from the mapping.
     * @throws OutOfBoundsException If the requested model does not exist.
     * @return array|string|null
     */
    public function modelMap($alias = null)
    {
        if ($this->modelMap === null) {
            $this->modelMap = $this->defaultModelMap();
        }

        if ($alias) {
            if (isset($this->modelMap[$alias])) {
                return $this->modelMap[$alias];
            } else {
                throw new OutOfBoundsException(sprintf(
                    'The model alias "%s" does not exist.',
                    $key
                ));
            }
        }

        return $this->modelMap;
    }

    /**
     * Replace the class-map with the given mapping.
     *
     * `[ 'alias' => 'Fully/Qualified/ClassName' ]`
     *
     * @param  array $models Map of aliases to models.
     * @return self
     */
    public function setModelMap(array $models)
    {
        $this->modelMap = array_replace(
            $this->defaultModelMap(),
            $models
        );

        return $this;
    }

    /**
     * Merge given mapping into the class-map.
     *
     * @param  array $models Map of aliases to models.
     * @return self
     */
    public function mergeModelMap(array $models)
    {
        $this->modelMap = array_replace(
            $this->defaultModelMap(),
            $this->modelMap,
            $models
        );

        return $this;
    }

    /**
     * Retrieve the default map of aliases to data models.
     *
     * @return array
     */
    protected function defaultModelMap()
    {
        return [
            'record' => ScrapeRecord::class
        ];
    }

    /**
     * Retrieve map of aliases to data models.
     *
     * @param  string|null $type Optional model data to retrieve from the dataset.
     * @return array
     */
    public function modelData($type = null)
    {
        if ($type) {
            if (isset($this->modelData[$type])) {
                return $this->modelData[$type];
            } else {
                return [];
            }
        }

        return $this->modelData;
    }

    /**
     * Replace the map of model data with the given data.
     *
     * `[ 'model-alias' => [ … ] ]`
     *
     * @param  string|array $type Optional model to associate given data to.
     * @param  array|null   $data Model data.
     * @throws InvalidArgumentException If data is missing.
     * @throws OutOfBoundsException If the requested model does not exist.
     * @return self
     */
    public function setModelData($type, array $data = null)
    {
        if (is_array($type)) {
            $data = $type;
            $type = null;
        } elseif (is_string($type)) {
            if (!$data) {
                throw new InvalidArgumentException(sprintf(
                    'Missing data for the given model "%s".',
                    $type
                ));
            }

            if (isset($this->modelData[$type])) {
                $this->modelData[$type] = array_replace_recursive(
                    $this->modelData,
                    $data
                );

                return $this;
            } else {
                throw new OutOfBoundsException(sprintf(
                    'The given model "%s" does not exist.',
                    $type
                ));
            }
        }

        $this->modelData = array_replace_recursive(
            $this->modelData,
            $data
        );

        return $this;
    }

    /**
     * Retrieve the web scraper request for the given key.
     *
     * @param  string $key The request to retrieve.
     * @throws OutOfBoundsException If the requested preset does not exist.
     * @return array
     */
    public function request($key)
    {
        if (!isset($this->requests[$key])) {
            throw new OutOfBoundsException(sprintf(
                'The request "%s" does not exist.',
                $key
            ));
        }

        return $this->requests[$key];
    }

    /**
     * Replace the web scraper request with the given data.
     *
     * @param  string     $key  Request key.
     * @param  array|null $data Request settings.
     * @return self
     */
    public function setRequest($key, array $data)
    {
        $this->requests[$key] = $data;

        return $this;
    }

    /**
     * Merge given data into the web scraper request.
     *
     * @param  string     $key  Request key.
     * @param  array|null $data Request settings.
     * @return self
     */
    public function mergeRequest($key, array $data)
    {
        if (isset($this->requests[$key])) {
            $this->requests[$key] = array_replace_recursive(
                $this->requests[$key],
                $data
            );
        } else {
            $this->setRequest($key, $data);
        }

        return $this;
    }

    /**
     * Retrieve the available web scraper requests.
     *
     * @return array
     */
    public function requests()
    {
        return $this->requests;
    }

    /**
     * Replace the web scraper requests with the given dataset.
     *
     * @param  array $requests New requests.
     * @return self
     */
    public function setRequests(array $requests)
    {
        $this->requests = $requests;

        return $this;
    }

    /**
     * Merge given dataset into the web scraper requests.
     *
     * @param  array $requests New requests.
     * @return self
     */
    public function mergeRequests(array $requests)
    {
        $this->requests = array_replace_recursive(
            $this->requests,
            $requests
        );

        return $this;
    }

    /**
     * Retrieve the default request preset.
     *
     * @return string
     */
    public function defaultRequest()
    {
        if ($this->defaultRequest === null) {
            return 'default';
        }

        return $this->defaultRequest;
    }

    /**
     * Set the default request preset.
     *
     * @param  string $key A preset request key.
     * @throws InvalidArgumentException If the request key is not a string.
     * @return self
     */
    public function setDefaultRequest($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                'Request key must be a string.'
            );
        }

        $this->defaultRequest = $key;

        return $this;
    }

    /**
     * Transform a snake_case string to camelCase.
     *
     * @see    \Charcoal\Config\AbstractEntity::camelize()
     * @param  string $str The snake_case string to camelize.
     * @return string The camelcase'd string.
     */
    protected function camelize($str)
    {
        return lcfirst(implode('', array_map('ucfirst', explode('_', $str))));
    }
}
