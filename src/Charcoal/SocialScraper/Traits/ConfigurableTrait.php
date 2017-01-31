<?php

namespace Charcoal\SocialScraper\Traits;

use OutOfBoundsException;

/**
 * Web Scraper Options Manager.
 *
 * @property array  $resolvedConfig  The resolved settings.
 * @property array  $defaultConfig   The default settings.
 * @property array  $immutableConfig The immutable settings.
 * @method   string camelize(string $str) Convert a value to camel case.
 */
trait ConfigurableTrait
{
    /**
     * Retrieve the web scraper configset or a given key's value.
     *
     * @param  string|null $key     Optional data key to retrieve from the configset.
     * @param  mixed|null  $default The default value to return if data key does not exist.
     * @return mixed|array
     */
    public function config($key = null, $default = null)
    {
        if ($key) {
            if (isset($this->resolvedConfig[$key])) {
                return $this->resolvedConfig[$key];
            } else {
                if (!is_string($default) && is_callable($default)) {
                    return $default();
                } else {
                    return $default;
                }
            }
        }

        return $this->resolvedConfig;
    }

    /**
     * Replace the web scraper configset with the given parameters.
     *
     * @param  array $config New config values.
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->resolvedConfig = array_replace_recursive(
            $this->defaultConfig(),
            $this->parseConfig($config),
            $this->immutableConfig()
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
        $this->resolvedConfig = array_replace_recursive(
            $this->defaultConfig(),
            $this->resolvedConfig,
            $this->parseConfig($config),
            $this->immutableConfig()
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
     * @return mixed|array
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
     * @return mixed|array
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
}
