<?php

namespace Charcoal\SocialScraper\Traits;

use ArrayAccess;
use RuntimeException;
use InvalidArgumentException;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\ScraperInterface;

/**
 * Common Scraper features for template controllers.
 */
trait ScraperAwareTrait
{
    /**
     * Store the available scrapers.
     *
     * @var ScraperInterface[]|null
     */
    protected $scrapers;

    /**
     * Set the available scrapers.
     *
     * @param  ScraperInterface[]|ArrayAccess $scrapers The collection of scrapers.
     * @throws InvalidArgumentException If the given collection is not array-accessible.
     * @return self
     */
    protected function setScrapers($scrapers)
    {
        if (!is_array($scrapers) && !($scrapers instanceof ArrayAccess)) {
            throw new InvalidArgumentException(
                'The collection of available scrapers must be an array or an instance of ArrayAccess.'
            );
        }

        $this->scrapers = $scrapers;

        return $this;
    }

    /**
     * Retrieve the available scrapers.
     *
     * @throws RuntimeException If scrapers are not available.
     * @return ScraperInterface[]
     */
    public function scrapers()
    {
        if (!isset($this->scrapers)) {
            throw new RuntimeException(
                sprintf('A collection of scrapers is required for "%s"', get_class($this))
            );
        }

        return $this->scrapers;
    }

    /**
     * Retrieve an available scraper.
     *
     * @param  string $key The scraper to retrieve.
     * @throws RuntimeException If tje are not available.
     * @return ScraperInterface
     */
    public function scraper($key)
    {
        return $this->scrapers()[$key];
    }

    /**
     * Retrieve the keys for the available scrapers.
     *
     * @return array
     */
    public function availableScrapers()
    {
        $scrapers = $this->scrapers();

        return ($scrapers instanceof \ArrayAccess) ? $scrapers->keys() : array_keys($scrapers);
    }
}
