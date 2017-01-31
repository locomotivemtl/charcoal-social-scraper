<?php

namespace Charcoal\SocialScraper\Script;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From Pimple
use Pimple\Container;

// From 'charcoal-admin'
use Charcoal\Admin\AdminScript;

// From 'charcoal-app'
use Charcoal\App\Script\ArgScriptTrait;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Traits\ConfigurableTrait;
use Charcoal\SocialScraper\Traits\ScraperAwareTrait;

/**
 * Basic Scraper Script
 */
abstract class AbstractScraperScript extends AdminScript
{
    use ArgScriptTrait;
    use ConfigurableTrait;
    use ScraperAwareTrait;

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
     * Parse command line arguments into script properties.
     *
     * Note: This method reroutes arguments to {@see ConfigurableTrait::mergeConfig()}.
     *
     * @see    ArgScriptTrait::parseArguments()
     * @return self
     */
    protected function parseArguments()
    {
        $cli  = $this->climate();
        $args = $cli->arguments;

        $ask     = $args->defined('interactive');
        $keys    = array_keys($this->defaultConfig());
        $params  = $this->arguments();
        $options = [];
        foreach ($params as $key => $param) {
            if (!in_array($key, $keys)) {
                continue;
            }

            $value = $args->get($key);
            if (!empty($value) || is_numeric($value)) {
                $options[$key] = $value;
            }

            if ($ask) {
                if (isset($param['prompt'])) {
                    $label = $param['prompt'];
                } else {
                    continue;
                }

                $value = $this->input($key);
                if (!empty($value) || is_numeric($value)) {
                    $options[$key] = $value;
                }
            }
        }

        $this->mergeConfig($options);

        return $this;
    }



    // CLI Arguments
    // =========================================================================

    /**
     * Retrieve the script's parent arguments.
     *
     * Useful for specialty classes extending this one that might not want
     * options for selecting specific objects.
     *
     * @return array
     */
    public function parentArguments()
    {
        return parent::defaultArguments();
    }
}
