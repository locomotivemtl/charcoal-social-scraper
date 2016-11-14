<?php

namespace Charcoal\SocialScraper\Traits;

use \RuntimeException;

// From `charcoal-social-scraper`
use \Charcoal\SocialScraper\InstagramScraper;
use \Charcoal\SocialScraper\TwitterScraper;

/**
 * Common Scraper features for template controllers.
 */
trait ScraperAwareTrait
{
    /**
     * Store the Instagram scraper instance for the current class.
     *
     * @var InstagramScraper
     */
    protected $instagramScraper;

    /**
     * Store the Twitter scraper instance for the current class.
     *
     * @var TwitterScraper
     */
    protected $twitterScraper;

    /**
     * Set the Instagram scraper.
     *
     * @param  Scraper $scrape The Instagram scraper instance.
     * @return self
     */
    protected function setInstagramScraper(InstagramScraper $scraper)
    {
        $this->instagramScraper = $scraper;

        return $this;
    }

    /**
     * Retrieve the Instagram scraper.
     *
     * @throws RuntimeException If the Instagram scraper was not previously set.
     * @return Scraper
     */
    public function instagramScraper()
    {
        if (!isset($this->instagramScraper)) {
            throw new RuntimeException(
                sprintf('Instagram scraper is not defined for "%s"', get_class($this))
            );
        }

        return $this->instagramScraper;
    }

    /**
     * Set the Twitter scraper.
     *
     * @param  TwitterScraper $scrape The Twitter scraper instance.
     * @return self
     */
    protected function setTwitterScraper(TwitterScraper $scraper)
    {
        $this->twitterScraper = $scraper;

        return $this;
    }

    /**
     * Retrieve the Twitter scraper.
     *
     * @throws RuntimeException If the Twitter scraper was not previously set.
     * @return TwitterScraper
     */
    public function twitterScraper()
    {
        if (!isset($this->twitterScraper)) {
            throw new RuntimeException(
                sprintf('Twitter scraper is not defined for "%s"', get_class($this))
            );
        }

        return $this->twitterScraper;
    }
}
