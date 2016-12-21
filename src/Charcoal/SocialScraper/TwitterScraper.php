<?php

namespace Charcoal\SocialScraper;

use DateTime;
use Exception;
use RuntimeException;
use InvalidArgumentException;

// From 'abraham/twitteroauth'
use Abraham\TwitterOAuth\TwitterOAuth as TwitterClient;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Exception\ApiResponseException;
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
    const NETWORK = 'twitter';

    /**
     * The social media network. Used by ScrapeRecord
     *
     * @var string
     */
    private $network = self::NETWORK;

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
     * @param  TwitterClient $client The client used to query the API.
     * @return self
     */
    public function setClient(TwitterClient $client)
    {
        $this->client = $client;

        return $this;
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
        $immutable = [ 'screen_name' => $this->config('user_id') ];

        return $this->scrapeTweets([
            'repository' => 'statuses',
            'method'     => 'user_timeline',
            'filters'    => array_replace_recursive($defaults, $filters, $immutable)
        ]);
    }

    /**
     * Scrape Twitter API and parse scraped data to create Charcoal models.
     *
     * @param  array $options Scraping options.
     * @throws ApiResponseException If something goes wrong with API calls.
     * @return ModelInterface[]|null
     */
    private function scrapeTweets(array $options = [])
    {
        if ($this->results === null) {
            // @todo This seems clumsy.
            $this->setConfig([ 'recordOptions' => $options ]);

            // Test for recent scrapes
            $record = $this->fetchRecentScrapeRecord();

            // An non-null ID means a recent record exists
            if ($record->id() !== null) {
                return $this->results;
            }

            $callApi   = true;
            $maxId     = null;
            $rawTweets = [];
            $models    = [];

            $defaults  = [
                'count' => 200
            ];
            $immutable = [];

            $filters = array_replace_recursive($defaults, $options['filters'], $immutable);

            // First, attempt fetching Twitter data through pagination
            try {
                $counter = 0;
                while ($callApi) {
                    if ($maxId !== null) {
                        $filters['max_id'] = $maxId;
                    }

                    $apiResponse = $this->client()->get($options['repository'].'/'.$options['method'], $filters);

                    if (!empty($apiResponse->errors)) {
                        $errors = $apiResponse->errors;
                        $messages = [];
                        foreach ($errors as $error) {
                            $messages[] = sprintf('Error: [%s] %s', $error->code, $error->message);
                        }
                        throw new ApiResponseException(implode('; ', $messages));
                    }

                    // Twitter is not consistent with its returned format
                    if (is_object($apiResponse) && property_exists($apiResponse, 'statuses')) {
                        $mergeReturn = $apiResponse->statuses;
                    } else {
                        $mergeReturn = $apiResponse;
                    }

                    $rawTweets = array_merge($rawTweets, $mergeReturn);

                    // Stop querying if we're getting less results than the "count" amount.
                    if (count($apiResponse) < $filters['count']) {
                        $callApi = false;
                    } else {
                        $maxId = (array_pop((array_slice($apiResponse, -1)))->id - 1);
                    }

                    $counter++;
                }
            } catch (Exception $e) {
                error_log(sprintf(
                    'Exception [%s] thrown in [%s]: %s',
                    get_class($e),
                    get_class($this),
                    $e->getMessage()
                ));
                return $this->results;
            }

            // Save the scrape record for caching purposes
            $record->save();

            // Loop through all media and store them with Charcoal if they don't already exist
            foreach ($rawTweets as $tweet) {
                $tweetModel = $this->modelFactory()->create(Tweet::class);

                if (!$tweetModel->source()->tableExists()) {
                    $tweetModel->source()->createTable();
                }

                $tweetModel->load($tweet->id);

                if ($tweetModel->id() === null) {
                    $tags = [];

                    foreach ($tweet->entities->hashtags as $tag) {
                        // Save the hashtags if not already saved
                        $tagModel = $this->modelFactory()->create(Tag::class);

                        if (!$tagModel->source()->tableExists()) {
                            $tagModel->source()->createTable();
                        }

                        $tagModel->load($tag->text);

                        if ($tagModel->id() === null) {
                            $tagModel->setData([
                                'id' => $tag->text
                            ]);
                            $tagModel->save();
                        }

                        $tags[] = $tagModel->id();
                    }

                    // Save the user if not already saved
                    $userData = $tweet->user;
                    $userModel = $this->modelFactory()->create(User::class);

                    if (!$userModel->source()->tableExists()) {
                        $userModel->source()->createTable();
                    }

                    $userModel->load($userData->id);

                    if ($userModel->id() === null) {
                        $userModel->setData([
                            'id'             => $userData->id,
                            'name'           => $userData->name,
                            'handle'         => $userData->screen_name,
                            'profilePicture' => $userData->profile_image_url_https
                        ]);
                        $userModel->save();
                    }

                    $tweetModel->setData([
                        'id'           => $tweet->id,
                        'created_data' => $tweet->created_at,
                        'tags'         => $tags,
                        'content'      => $tweet->text,
                        'user'         => $userModel->id(),
                        'raw_data'     => json_encode((array)$tweet)
                    ]);

                    $tweetModel->save();
                }

                $models[] = $tweetModel;
            }

            $this->setResults($models);
        }

        return $this->results;
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
