<?php

namespace Charcoal\SocialScraper;

use \DateTime;
use \Exception;
use \InvalidArgumentException;

// Module `charcoal-factory` dependencies
use \Charcoal\Factory\FactoryInterface;

// From `charcoal-social-scraper`
use \Charcoal\SocialScraper\Object\ScrapeRecord;

/**
 * Generic scraping class intended for connection to a social network API
 * through a client and saving results as Charcoal Objects.
 */
abstract class AbstractScraper
{
    /**
     * @var FactoryInterface $modelFactory
     */
    protected $modelFactory;

    /**
     * The project configuration container.
     *
     * @var \Charcoal\Config\ConfigInterface|null;
     */
    protected $appConfig;

    /**
     * @var mixed $client
     */
    protected $client;

    /**
     * @var array $results
     */
    protected $results;

    /**
     * Default configuration.
     *
     * @var array $config
     */
    private $config = [
        'record' => true,
        'recordExpires' => '1 hour',
        'recordOptions' => [
            'network' => '',
            'repository' => '',
            'method' => '',
            'filter' => ''
        ]
    ];

    /**
     * @param array $data The constructor options.
     * @return void
     */
    public function __construct(array $data)
    {
        $this->appConfig = $data['config'];
        $this->setClient($data['client']);
        $this->setModelFactory($data['model_factory']);
    }

    /**
     * @param FactoryInterface $factory The factory used to create logs and models.
     * @return void
     */
    private function setModelFactory(FactoryInterface $factory)
    {
        $this->modelFactory = $factory;
    }

    /**
     * @throws Exception If the model factory was not properly set.
     * @return FactoryInterface
     */
    protected function modelFactory()
    {
        if ($this->modelFactory === null) {
            throw new Exception(
                'Can not access model factory, the dependency has not been set.'
            );
        }
        return $this->modelFactory;
    }

    /**
     * Attempt to get the latest ScrapeRecord according to specific properties
     *
     * @throws Exception If the required arguments are not supplied.
     * @return ModelInterface  A ScrapeRecord instance.
     */
    public function fetchRecentScrapeRecord()
    {
        $config = $this->config();
        $recordOptions = $config['recordOptions'];

        // Required values
        if (
            empty($recordOptions['network']) ||
            empty($recordOptions['repository']) ||
            empty($recordOptions['method']) ||
            empty($recordOptions['filters'])
        ) {
            throw new Exception(
                'Can not create a ScrapeRecord, the config has not been properly set.'
            );
        }

        // Create a proto model to generate the ident
        $proto = $this->modelFactory()
            ->create(ScrapeRecord::class)
            ->setData([
                'network' => $recordOptions['network'],
                'repository' => $recordOptions['repository'],
                'method' => $recordOptions['method'],
                'filters' => $recordOptions['filters']
            ]);

        if (!$proto->source()->tableExists()) {
            $proto->source()->createTable();
        }

        //@todo Config setter should create DateTime
        $earlierDate = new DateTime('now - '. $config['recordExpires']);

        // Query the DB for an existing record in the past hour
        $model = $this->modelFactory()
            ->create(ScrapeRecord::class)
            ->loadFromQuery('
                SELECT * FROM `' . $proto->source()->table() . '`
                WHERE
                    `ident` = :ident
                ORDER BY
                    `ts` DESC
                LIMIT 1',
                [
                    'ident' => $proto->generateIdent()
                ]
            );

        return ($earlierDate > $model->ts()) ? $proto : $model;
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
     * @param ModelInterface[]|array|null
     * @return self
     */
    public function setResults($results)
    {
        $this->results = $results;

        return $this;
    }

    /**
     * Merge defaults with supplied parameters.
     *
     * @param array $config New config values.
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = array_replace_recursive($this->config, $config);

        return $this;
    }

    /**
     * Retrieve the scraper config
     *
     * @return array
     */
    public function config()
    {
        return $this->config;
    }
}
