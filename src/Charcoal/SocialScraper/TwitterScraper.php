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
use \Charcoal\Twitter\Object\Tag;
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
     * Scrape Twitter API according to hashtag.
     *
     * @param  string $tag The searched tag.
     * @throws InvalidArgumentException If the query is not a string or is empty.
     * @return ModelInterface[]|null
     */
    public function scrapeByTag($tag)
    {
        if (!is_string($tag)) {
            throw new InvalidArgumentException(
                'Scraped tag must be a string.'
            );
        }

        if ($tag == '') {
            throw new InvalidArgumentException(
                'Tag can not be empty.'
            );
        }

        // $data = $this->client()->get($options['repository'] . '/' . $options['method'], [
        //     // 'q' => '#ufootball',
        //     // 'q' => '#Vanier52',
        //     'include_entities' => true,
        //     // 'q' => 'from:' . $this->appConfig['twitter.user_id'],
        //     // 'q' => '#Vanier52 AND from:' . $this->appConfig['twitter.user_id'],
        //     // 'q' => '#ufootball AND from:' . $this->appConfig['twitter.user_id'],
        //     'q' => '#ufootball AND from:usportsca',
        //     'count' => 40
        // ]);

        return $this->scrapeTweets([
            'repository' => 'search',
            'method' => 'tweets',
            'filters' => [
                'q' => '#' . $tag . ' AND from:' . $this->appConfig['twitter.user_id'],
                'include_entities' => true
            ]
        ]);
    }

    /**
     * Scrape Twitter API for all posts by authorized user.
     * Risky thing to do. Will most certainly break the 15 minute rate limit.
     *
     * @return ModelInterface[]|null
     */
    public function scrapeAll()
    {
        // $data = $this->twitterConnection()->get('statuses/user_timeline', [
        //     'screen_name' => $this->appConfig['twitter.user_id'],
        //     // 'count' => 5
        // ]);

        return $this->scrapeTweets([
            'repository' => 'statuses',
            'method' => 'user_timeline',
            'filters' => [
                'screen_name' => $this->appConfig['twitter.user_id']
            ]
        ]);
    }

    /**
     * Scrape Twitter API and parse scraped data to create Charcoal models.
     *
     * @param  array  $options  Scraping options.
     * @throws Exception If something goes wrong with API calls.
     * @return ModelInterface[]|null
     */
    private function scrapeTweets(array $options = [])
    {
        if ($this->results === null) {
            //@todo This seems clumsy.
            $config = ['recordOptions' => $options];
            $config['recordOptions']['network'] = $this->network();
            $this->setConfig($config);

            // Test for recent scrapes
            $record = $this->fetchRecentScrapeRecord();

            // An non-null ID means a recent record exists
            if ($record->id() !== null) {
                return $this->results;
            }

            $callApi = true;
            $max_id = null;
            $count = 200;
            $rawTweets = [];
            $models = [];

            $filters = $options['filters'];
            $filters['count'] = $count;

            // First, attempt fetching Twitter data through pagination
            try {
                $counter = 0;
                while ($callApi) {
                    if ($max_id !== null) {
                        $filters['max_id'] = $max_id;
                    }

                    $apiResponse = $this->client()->get($options['repository'] . '/' . $options['method'], $filters);

                    if (!empty($apiResponse->errors)) {
                        $errors = $apiResponse->errors;
                        $messages = [];
                        foreach ($errors as $error) {
                            $messages[] = sprintf('Error code %s. Message: %s.', $error->code, $error->message);
                        }
                        throw new Exception(implode(' ', $messages));
                    }

                    // Twitter is not consistent with its returned format
                    $rawTweets = array_merge($rawTweets, (is_object($apiResponse) && property_exists($apiResponse, 'statuses') ? $apiResponse->statuses : $apiResponse));

                    // Stop querying if we're getting less results than the $count amount.
                    if (count($apiResponse) < $count) {
                        $callApi = false;
                    } else {
                        $max_id = (array_pop((array_slice($apiResponse, -1)))->id - 1);
                    }

                    $counter = $counter + 1;
                }
            } catch (Exception $e) {
                error_log('Fatal exception');
                error_log(get_class($e));
                error_log($e->getMessage());
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

                    foreach($tweet->entities->hashtags as $tag) {
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
                            'id' => $userData->id,
                            'name' => $userData->name,
                            'handle' => $userData->screen_name,
                            'profilePicture' => $userData->profile_image_url_https
                        ]);
                        $userModel->save();
                    }

                    $tweetModel->setData([
                        'id'      => $tweet->id,
                        'created' => $tweet->created_at,
                        'tags'    => $tags,
                        'content' => $tweet->text,
                        'user'    => $userModel->id(),
                        'json'    => json_encode((array)$tweet)
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
