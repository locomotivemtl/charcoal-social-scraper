<?php

namespace Charcoal\SocialScraper\Action;

use OutOfBoundsException;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From Pimple
use Pimple\Container;

// From 'charcoal-admin'
use Charcoal\Admin\AdminAction;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Traits\ScraperAwareTrait;
use Charcoal\SocialScraper\ScraperInterface;

/**
 * Basic Scraper Action
 */
abstract class AbstractScraperAction extends AdminAction
{
    use ScraperAwareTrait;

    /**
     * Store the HTTP request object.
     *
     * @var RequestInterface
     */
    protected $httpRequest;

    /**
     * Store the HTTP response object.
     *
     * @var ResponseInterface
     */
    protected $httpResponse;

    /**
     * Importation configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $defaultConfig = [];

    /**
     * Inject dependencies from a DI Container.
     *
     * @param  Container $container A dependencies container instance.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setScrapers($container['charcoal/social/scrapers']);
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
     * Replace the action's configset with the given parameters.
     *
     * @param  array $config New config values.
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = array_replace_recursive(
            $this->defaultConfig(),
            $this->parseConfig($config)
        );

        return $this;
    }

    /**
     * Merge given parameters into the action's configset.
     *
     * @param  array $config New config values.
     * @return self
     */
    public function mergeConfig(array $config)
    {
        $this->config = array_replace_recursive(
            $this->defaultConfig(),
            $this->config,
            $this->parseConfig($config)
        );

        return $this;
    }

    /**
     * Merge Parse parameters for the action's configset.
     *
     * @param  array $config Raw config values.
     * @return array
     */
    protected function parseConfig(array $config)
    {
        $dataset = [];
        foreach ($config as $key => $val) {
            $parser = $this->parser($key);
            if (is_callable([ $this, $parser ])) {
                $dataset[$key] = $this->{$parser}($val, $key);
            } else {
                $dataset[$key] = $val;
            }
        }

        return $dataset;
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
     * Retrieve the parser method for a given key.
     *
     * @param  string $key The key to get the parser from.
     * @return string The parser method name.
     */
    protected function parser($key)
    {
        return $this->camelize('parse_'.$key);
    }

    /**
     * Retrieve the HTTP request.
     *
     * @throws RuntimeException If the HTTP request was not previously set.
     * @return RequestInterface
     */
    public function httpRequest()
    {
        if (!isset($this->httpRequest)) {
            throw new RuntimeException(
                sprintf('HTTP Request is not defined for "%s"', get_class($this))
            );
        }

        return $this->httpRequest;
    }

    /**
     * Set an HTTP request object.
     *
     * @param  RequestInterface $request A PSR-7 compatible Request instance.
     * @return self
     */
    protected function setHttpRequest(RequestInterface $request)
    {
        $this->httpRequest = $request;

        return $this;
    }

    /**
     * Retrieve the HTTP response.
     *
     * @throws RuntimeException If the HTTP response was not previously set.
     * @return ResponseInterface
     */
    public function httpResponse()
    {
        if (!isset($this->httpResponse)) {
            throw new RuntimeException(
                sprintf('HTTP Response is not defined for "%s"', get_class($this))
            );
        }

        return $this->httpResponse;
    }

    /**
     * Set an HTTP response object.
     *
     * @param  ResponseInterface $response A PSR-7 compatible Response instance.
     * @return self
     */
    protected function setHttpResponse(ResponseInterface $response)
    {
        $this->httpResponse = $response;

        return $this;
    }

    /**
     * Update the action's {@see ResponseInterface} with the specified status code and,
     * optionally, reason phrase.
     *
     * @param integer $code         The 3-digit integer result code to set.
     * @param string  $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     * @return self
     */
    protected function updateResponseWithStatus($code, $reasonPhrase = '')
    {
        $this->httpResponse = $this->httpResponse->withStatus($code, $reasonPhrase);

        return $this;
    }

    /**
     * Is this response successful?
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        $response = $this->httpResponse();

        return ($response->isSuccessful() || $response->isInformational());
    }
}
