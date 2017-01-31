<?php

namespace Charcoal\SocialScraper\Traits;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Exception\MissingOptionsException;

/**
 * Common Scraper features for import requests.
 */
trait ImportableTrait
{
    /**
     * The resolved settings.
     *
     * @var array
     */
    protected $resolvedConfig = [];

    /**
     * The default settings.
     *
     * Note: Only "scrapers" is supported.
     *
     * @var array
     */
    protected $defaultConfig = [
        'scrapers'    => [],
        'request'     => null,
        'count'       => null,
        'tags'        => [],
        'user_id'     => null,
        'screen_name' => null,
    ];

    /**
     * The immutable settings.
     *
     * @var array
     */
    protected $immutableConfig = [];

    /**
     * @param  mixed  $scrapers One or more scrapers.
     * @param  string $key      The confiset's data key.
     * @throws MissingOptionsException If requested scrapers are unsupported.
     * @return ScraperInterface[]
     */
    public function parseScrapers($scrapers, &$key)
    {
        $key = 'scrapers';

        $available = $this->availableScrapers();
        $choices   = $scrapers;
        $scrapers  = [];
        if (!empty($choices)) {
            if (!is_array($choices)) {
                $choices = explode(',', $choices);
            }

            $scrapers = array_intersect($available, array_unique($choices));
        }

        if (!$scrapers) {
            throw new MissingOptionsException(sprintf(
                'Missing scraper(s). Available scrapers are: "%s".',
                implode('", "', $available)
            ));
        }

        return $scrapers;
    }

    /**
     * Alias of {@see self::parseScrapers()}.
     *
     * @param  mixed  $scrapers One or more scrapers to be scraped.
     * @param  string $key      The confiset's data key.
     * @return ScraperInterface[]
     */
    public function parseScraper($scrapers, &$key)
    {
        return $this->parseScrapers($scrapers, $key);
    }

    /**
     * @param  mixed  $tags One or more "hashtags" to filter posts by.
     * @param  string $key  The confiset's data key.
     * @return string[]
     */
    public function parseTags($tags, &$key = null)
    {
        $key = 'tags';

        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }

        foreach ($tags as &$tag) {
            if (0 === strpos($tag, '#')) {
                $tag = substr($tag, 1);
            }
        }

        return $tags;
    }

    /**
     * Alias of {@see self::parseTags()}.
     *
     * @param  mixed  $tags One or more "hashtags" to filter posts by.
     * @param  string $key  The confiset's data key.
     * @return string[]
     */
    public function parseTag($tags, &$key)
    {
        return $this->parseTags($tags, $key);
    }

    /**
     * Import Posts
     *
     * @return self
     */
    abstract public function import();
}
