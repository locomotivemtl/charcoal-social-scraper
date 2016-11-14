<?php

namespace Charcoal\SocialScraper;

use \DateTime;
use \Exception;
use \InvalidArgumentException;

// From `abraham/twitteroauth`
use \Abraham\TwitterOAuth\TwitterOAuth as TwitterClient;

// From `charcoal-social-scraper`
use \Charcoal\SocialScraper\AbstractScraper;
use \Charcoal\Twitter\Object\Tweet;
use \Charcoal\Twitter\Object\Hashtag;
use \Charcoal\Twitter\Object\User;

/**
 * Scraping class that connects to Twitter API and converts tweets/users/hashtags to Charcoal Objects.
 */
class TwitterScraper extends AbstractScraper implements
    ScraperInterface
{
    /**
     * The social media network. Used by ScrapeRecord
     *
     * @var string
     */
    private $network = 'twitter';

    /**
     * @param TwitterClient $client The Twitter Client used to query the API.
     * @throws Exception If the supplied client is not a proper TwitterClient.
     * @return self
     */
    public function setClient($client)
    {
        if (!$client instanceof TwitterClient) {
            throw new Exception(
                'The client must be an instance of \Abraham\TwitterOAuth\TwitterOAuth.'
            );
        }

        $this->client = $client;

        return $this;
    }

    /**
     * Retrieve the Twitter Client.
     *
     * @throws Exception If the Twitter client was not properly set.
     * @return TwitterClient
     */
    public function client()
    {
        if ($this->client === null) {
            throw new Exception(
                'Can not access Twitter client, the dependency has not been set.'
            );
        }
        return $this->client;
    }

    /**
     * Retrieve the social media network.
     *
     * @return string
     */
    public function network()
    {
        return $this->network;
    }
}
