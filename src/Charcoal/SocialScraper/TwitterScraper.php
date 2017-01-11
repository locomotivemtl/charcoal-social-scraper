<?php

namespace Charcoal\SocialScraper;

use DateTime;
use DateTimeImmutable;
use RuntimeException;
use InvalidArgumentException;

// From 'abraham/twitteroauth'
use Abraham\TwitterOAuth\TwitterOAuth as TwitterClient;
use Abraham\TwitterOAuth\TwitterOAuthException;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Exception\Exception as ScraperException;
use Charcoal\SocialScraper\Exception\ApiException;
use Charcoal\SocialScraper\Exception\HitException;
use Charcoal\SocialScraper\Exception\NotFoundException;
use Charcoal\SocialScraper\Exception\RateLimitException;
use Charcoal\SocialScraper\AbstractScraper;
use Charcoal\SocialScraper\ScraperInterface;

use Charcoal\Twitter\Object\Tweet;
use Charcoal\Twitter\Object\Tag;
use Charcoal\Twitter\Object\User;

/**
 * Scraping class that connects to Twitter API and converts tweets/users/hashtags to Charcoal Objects.
 */
class TwitterScraper extends AbstractScraper implements
    ScraperInterface
{
    /**
     * The network key. For logging, actions, and scripts.
     *
     * @const string
     */
    const NETWORK = 'twitter';

    /**
     * Data is {@link https://dev.twitter.com/rest/reference/get/statuses/user_timeline restricted to}
     * 3,200 of a user’s most recent Tweets.
     *
     * @const integer
     */
    const API_TWEET_LIMIT = 200;

    /**
     * Immutable configuration.
     *
     * @var array
     */
    protected $immutableConfig = [
        'recordOptions' => [
            'network'   => self::NETWORK
        ]
    ];

    /**
     * Retrieve the social media network.
     *
     * @return string
     */
    public function network()
    {
        return self::NETWORK;
    }

    /**
     * Retrieve the Twitter Client.
     *
     * @throws RuntimeException If the client was not properly set.
     * @return TwitterClient
     */
    public function client()
    {
        if ($this->client === null) {
            throw new RuntimeException(sprintf(
                'Can not access %s, the dependency has not been set.',
                TwitterClient::class
            ));
        }

        return $this->client;
    }

    /**
     * Set the Twitter Client.
     *
     * @param  TwitterClient $client The client used to query the API.
     * @return self
     */
    public function setClient(TwitterClient $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Retrieve the default map of aliases to data models.
     *
     * @return array
     */
    protected function defaultModelMap()
    {
        $map = [
            'tweet' => Tweet::class,
            'tag'   => Tag::class,
            'user'  => User::class,
        ];

        return array_merge(parent::defaultModelMap(), $map);
    }

    /**
     * Scrape Twitter API according to hashtag.
     *
     * @param  string $tag     The searched tag.
     * @param  array  $filters Modify the API request.
     * @throws InvalidArgumentException If the tag is invalid.
     * @return ModelInterface[]|null
     */
    public function scrapeByTag($tag, array $filters = [])
    {
        if (!is_string($tag)) {
            throw new InvalidArgumentException(
                'Tag must be a string.'
            );
        }

        if (0 === strpos($tag, '#')) {
            $tag = substr($tag, 1);
        }

        if ($tag == '') {
            throw new InvalidArgumentException(
                'Tag can not be empty.'
            );
        }

        $defaults  = [
            'include_entities' => true
        ];
        $immutable = [
            'q' => sprintf('#%s AND from:%s', $tag, $this->config('user_id')),
        ];

        return $this->scrapeTweets([
            'repository' => 'search',
            'method'     => 'tweets',
            'filters'    => array_replace_recursive($defaults, $filters, $immutable)
        ]);
    }

    /**
     * Scrape Twitter API for all posts by authorized user.
     * Risky thing to do. Will most certainly break the 15 minute rate limit.
     *
     * @param  array $filters Modify the API request.
     * @return ModelInterface[]|null
     */
    public function scrapeAll(array $filters = [])
    {
        $defaults  = [];
        $immutable = [];

        $screenName = $this->config('screen_name');
        if ($screenName) {
            $immutable['screen_name'] = $screenName;
        } else {
            $userId = $this->config('user_id');
            if ($screenName) {
                $immutable['user_id'] = $userId;
            }
        }

        return $this->scrapeTweets([
            'repository' => 'statuses',
            'method'     => 'user_timeline',
            'filters'    => array_replace_recursive($defaults, $filters, $immutable)
        ]);
    }

    /**
     * Scrape Twitter API for all posts by authorized user.
     *
     * @param  string|array|null $request Either a preset request key or a request config.
     *     If no $settings are supplied, the default preset request is used.
     * @param  array|null        $params  Custom scraping options.
     * @return ModelInterface[]|null
     */
    public function scrape($request = null, array $params = null)
    {
        $params = $this->parseScrapeRequest($request, $params);

        return $this->scrapeTweets($params);
    }

    /**
     * Scrape Twitter API and parse scraped data to create Charcoal models.
     *
     * @param  array $params Scraping options.
     * @return ModelInterface[]|null
     */
    private function scrapeTweets(array $params = [])
    {
        if ($this->results === null) {
            $time    = new DateTimeImmutable();
            $results = $this->fetchTweetsFromApi($params);

            if ($results === null) {
                return $this->results;
            }

            // Loop through all media and store them with Charcoal if they don't already exist
            $posts = [];
            foreach ($results as $tweetData) {
                $tweetModel = $this->createModel('tweet');

                if ($tweetModel->source()->tableExists()) {
                    $tweetModel->load($tweetData->id);
                }

                if ($tweetModel->id() === null) {
                    // Save the hashtags if not already saved
                    $tags = [];
                    if (isset($tweetData->entities->hashtags)) {
                        foreach ($tweetData->entities->hashtags as $tagData) {
                            $tagModel = $this->createModel('tag');

                            if ($tagModel->source()->tableExists()) {
                                $tagModel->load($tagData->text);
                            }

                            if ($tagModel->id() === null) {
                                $tagModel->setData([
                                    'id' => $tagData->text
                                ]);
                                $tagModel->save();
                            }

                            $tags[] = $tagModel->id();
                        }
                    }

                    // Save the user if not already saved
                    $userData  = $tweetData->user;
                    $userModel = $this->createModel('user');

                    if ($userModel->source()->tableExists()) {
                        $userModel->load($userData->id);
                    }

                    if ($userModel->id() === null) {
                        $userModel->setData([
                            'id'           => $userData->id,
                            'created_date' => $time->modify($userData->created_at),
                            'handle'       => $userData->screen_name,
                            'name'         => $userData->name,
                            'avatar'       => $userData->profile_image_url_https
                        ]);
                        $userModel->save();
                    }

                    $tweetModel->setData([
                        'id'           => $tweetData->id,
                        'created_date' => $time->modify($tweetData->created_at),
                        'tags'         => $tags,
                        'text'         => $tweetData->text,
                        'user'         => $userModel->id(),
                        'raw_data'     => json_encode((array)$tweetData)
                    ]);

                    $tweetModel->save();
                }

                $posts[] = $tweetModel;
            }

            $this->setResults($posts);
        }

        return $this->results;
    }

    /**
     * Fetch tweets from the Twitter API.
     *
     * @param  array   $params Scraping options.
     * @param  boolean $force  Force a new request to the API.
     *     This will save a new scrape record.
     * @throws HitException If the Twitter was recently scraped.
     * @throws NotFoundException If the API endpoint does not exist.
     * @throws RateLimitException If the too many requests have been made.
     * @throws OAuthException If the request failed (OAuth).
     * @throws ApiException If the request failed (API).
     * @return array|null
     */
    private function fetchTweetsFromApi(array $params = [], $force = false)
    {
        if ($params) {
            // @todo This seems clumsy.
            $this->mergeConfig([ 'recordOptions' => $params ]);
        }

        if ($force) {
            $record = $this->createScrapeRecord();
        } else {
            // Test for recent scrapes
            $record = $this->fetchRecentScrapeRecord();

            // If the record has an ID, that means a recent record exists.
            if ($record->id() !== null) {
                $time = $record->logDate();
                $time->modify('+'.$this->config('recordExpires'));
                $message = sprintf(
                    'Expires on %s',
                    $time->format(DateTime::RSS)
                );

                throw new HitException($message);
            }
        }

        $callApi  = true;
        $results  = [];
        $params   = $this->config('recordOptions');
        $defaults = [
            'count'    => null,
            'max_id'   => null,
            'since_id' => null
        ];
        $filters = array_replace($defaults, $params['filters']);

        if ($filters['since_id'] === null) {
            $tweet = $this->fetchLatestTweet();
            if ($tweet) {
                $filters['since_id'] = $tweet->id();
            }
        }

        $filters = array_filter($filters, function ($param) {
            return $param !== null;
        });

        try {
            $client = $this->client();
            while ($callApi) {
                $response = $client->get($params['repository'].'/'.$params['method'], $filters);

                if (!empty($response->errors)) {
                    $errors   = $response->errors;
                    $messages = [];
                    foreach ($errors as $error) {
                        $record->setStatus($error->code);
                        $messages[] = sprintf('[%s] %s', $error->code, $error->message);
                    }
                    throw new ApiException(implode("; \n", $messages));
                }

                // Twitter is not consistent with its returned format
                if (is_object($response) && property_exists($response, 'statuses')) {
                    $statuses = $response->statuses;
                } else {
                    $statuses = $response;
                }

                $results = array_merge($results, $statuses);

                // Stop querying if we're getting less results than the "count" amount.
                $count = count($statuses);
                if ($count === 0 || (isset($filters['count']) && $count < $filters['count'])) {
                    $callApi = false;
                } else {
                    $last = end($statuses);
                    if (isset($last->id)) {
                        $filters['max_id'] = ($last->id - 1);
                    } else {
                        $callApi = false;
                    }
                }
            }
        } catch (TwitterOAuthException $e) {
            $httpCode = $client->getLastHttpCode();
            $message  = sprintf(
                'Exception [%s]: %s',
                $class,
                $e->getMessage()
            );
            $record->setStatus($record::STATUS_FAIL);

            switch ($httpCode) {
                case 401:
                case 403:
                    throw new OAuthException($message, 0, $e);

                case 404:
                case 410:
                    throw new NotFoundException($message, 0, $e);

                case 420:
                case 429:
                    throw new RateLimitException($message, 0, $e);
            }

            throw new ApiException($message, 0, $e);
        } finally {
            // Save the scrape record for caching purposes
            $record->save();
        }

        return $results;
    }

    /**
     * Attempt to get the latest tweet.
     *
     * @return ModelInterface|null
     */
    public function fetchLatestTweet()
    {
        $obj = $this->createModel('tweet', false);
        $obj->loadFromQuery('SELECT * FROM `'.$obj->source()->table().'` ORDER BY `created_date` DESC LIMIT 1');

        if ($obj->id()) {
            return $obj;
        }

        return null;
    }
}
