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

    public function scrapeAll()
    {
        // $data = $this->twitterConnection()->get('statuses/user_timeline', [
        //     'screen_name' => $this->appConfig['twitter.user_id'],
        //     // 'count' => 5
        // ]);
        $data = $this->client()->get('search/tweets', [
            // 'q' => '#ufootball',
            // 'q' => '#Vanier52',
            'include_entities' => true,
            // 'q' => 'from:' . $this->appConfig['twitter.user_id'],
            // 'q' => '#Vanier52 AND from:' . $this->appConfig['twitter.user_id'],
            // 'q' => '#ufootball AND from:' . $this->appConfig['twitter.user_id'],
            'q' => '#ufootball AND from:usportsca',
            'count' => 40
        ]);

        // Twitter is not consistent with its returned format
        $rawTweets = is_object($data) && property_exists($data, 'statuses') ? $data->statuses : $data;

        $rootUrl = 'https://www.twitter.com/';
        $tweets = [];

        // var_dump(count($rawTweets));

        foreach ($rawTweets as $tweet) {

            // var_dump($tweet->entities);
            // var_dump($rootUrl . $tweet->user->screen_name . '/status/' . $tweet->id);

            $tweets[] = [
                'id' => $tweet->id,
                'url' => $rootUrl . $tweet->user->screen_name . '/status/' . $tweet->id,
                'date' => $tweet->created_at,
                'user' => [
                    'name' => $tweet->user->name,
                    'screenName' => $tweet->user->screen_name,
                    'url' => $rootUrl . $tweet->user->screen_name
                ],
                'content' => $this->linkifyTwitterStatus($tweet->text)
            ];
        }

        // var_dump($rawTweets);
        // die();

        return $tweets;
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
