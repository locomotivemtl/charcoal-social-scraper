<?php

namespace Charcoal\SocialScraper\Traits;

use RuntimeException;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\ScraperInterface;
use Charcoal\SocialScraper\InstagramScraper;
use Charcoal\SocialScraper\TwitterScraper;

/**
 * Common Scraper features for template controllers.
 */
trait ScraperAwareTrait
{
    /**
     * Store the Instagram scraper instance for the current class.
     *
     * @var ScraperInterface|null
     */
    protected $instagramScraper;

    /**
     * Store the Twitter scraper instance for the current class.
     *
     * @var ScraperInterface|null
     */
    protected $twitterScraper;

    /**
     * Set the Instagram scraper.
     *
     * @param  InstagramScraper $scraper The Instagram scraper instance.
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
     * @return ScraperInterface
     */
    public function instagramScraper()
    {
        if (!isset($this->instagramScraper)) {
            throw new RuntimeException(
                sprintf('Instagram Scraper is not defined for "%s"', get_class($this))
            );
        }

        return $this->instagramScraper;
    }

    /**
     * Set the Twitter scraper.
     *
     * @param  TwitterScraper $scraper The Twitter scraper instance.
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
     * @return ScraperInterface
     */
    public function twitterScraper()
    {
        if (!isset($this->twitterScraper)) {
            throw new RuntimeException(
                sprintf('Twitter Scraper is not defined for "%s"', get_class($this))
            );
        }

        return $this->twitterScraper;
    }
}
