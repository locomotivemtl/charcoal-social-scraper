<?php

namespace Charcoal\SocialScraper;

use DateTime;
use DateTimeImmutable;
use RuntimeException;
use InvalidArgumentException;

// From 'larabros/elogram'
use Larabros\Elogram\Client as InstagramClient;
use Larabros\Elogram\Exceptions\Exception as ElogramException;
use Larabros\Elogram\Exceptions\APINotFoundError;
use Larabros\Elogram\Exceptions\OAuthRateLimitException;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Exception\Exception as ScraperException;
use Charcoal\SocialScraper\Exception\ApiException;
use Charcoal\SocialScraper\Exception\HitException;
use Charcoal\SocialScraper\Exception\NotFoundException;
use Charcoal\SocialScraper\Exception\RateLimitException;
use Charcoal\SocialScraper\AbstractScraper;
use Charcoal\SocialScraper\ScraperInterface;

use Charcoal\Instagram\Object\Media;
use Charcoal\Instagram\Object\Tag;
use Charcoal\Instagram\Object\User;

/**
 * Scraping class that connects to Instagram API and converts medias/users/tags to Charcoal Objects.
 */
class InstagramScraper extends AbstractScraper implements
    ScraperInterface
{
    /**
     * The network key. For logging, actions, and scripts.
     *
     * @const string
     */
    const NETWORK = 'instagram';

    /**
     * Data is {@link https://www.instagram.com/developer/sandbox/#api-behavior restricted to sandbox users}
     * and the 20 most recent media from each sandbox user.
     *
     * @const integer
     */
    const API_SANDBOX_USER_MEDIA_LIMIT = 20;

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
     * Retrieve the Instagram Client.
     *
     * @throws RuntimeException If the client was not properly set.
     * @return InstagramClient
     */
    public function client()
    {
        if ($this->client === null) {
            throw new RuntimeException(sprintf(
                'Can not access %s, the dependency has not been set.',
                InstagramClient::class
            ));
        }

        return $this->client;
    }

    /**
     * Set the Instagram Client.
     *
     * @param  InstagramClient $client The client used to query the API.
     * @return self
     */
    public function setClient(InstagramClient $client)
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
            'media' => Media::class,
            'tag'   => Tag::class,
            'user'  => User::class,
        ];

        return array_merge(parent::defaultModelMap(), $map);
    }

    /**
     * Scrape Instagram API according to hashtag.
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

        $defaults  = [];
        $immutable = [ 'tag' => $tag ];

        return $this->scrapeMedia([
            'repository' => 'tags',
            'method'     => 'getRecentMedia',
            'filters'    => array_replace_recursive($defaults, $filters, $immutable)
        ]);
    }

    /**
     * Scrape Instagram API for all posts by authorized user.
     *
     * @param  array $filters Modify the API request.
     * @return ModelInterface[]|null
     */
    public function scrapeAll(array $filters = [])
    {
        $defaults  = [];
        $immutable = [ 'id' => 'self' ];

        return $this->scrapeMedia([
            'repository' => 'users',
            'method'     => 'getMedia',
            'filters'    => array_replace_recursive($defaults, $filters, $immutable)
        ]);
    }

    /**
     * Scrape Instagram API for all posts by authorized user.
     *
     * @param  string|array|null $request Either a preset request key or a request config.
     *     If no $settings are supplied, the default preset request is used.
     * @param  array|null        $params  Custom scraping options.
     * @return ModelInterface[]|null
     */
    public function scrape($request = null, array $params = null)
    {
        $params = $this->parseScrapeRequest($request, $params);

        return $this->scrapeMedia($params);
    }

    /**
     * Scrape Instagram API and parse scraped data to create Charcoal models.
     *
     * @param  array $params Scraping options.
     * @return ModelInterface[]|null
     */
    private function scrapeMedia(array $params = [])
    {
        if ($this->results === null) {
            $time    = new DateTimeImmutable();
            $results = $this->fetchMediaFromApi($params);

            if ($results === null) {
                return $this->results;
            }

            // Loop through all media and store them with Charcoal if they don't already exist
            $posts = [];
            foreach ($results as $mediaData) {
                $mediaModel = $this->createModel('media');

                if ($mediaModel->source()->tableExists()) {
                    $mediaModel->load($mediaData['id']);
                }

                if ($mediaModel->id() === null) {
                    // Save the hashtags if not already saved
                    $tags = [];
                    if (isset($mediaData['tags'])) {
                        foreach ($mediaData['tags'] as $tagId) {
                            $tagModel = $this->createModel('tag');

                            if ($tagModel->source()->tableExists()) {
                                $tagModel->load($tagId);
                            }

                            if ($tagModel->id() === null) {
                                $tagModel->setData([
                                    'id' => $tagId
                                ]);
                                $tagModel->save();
                            }

                            $tags[] = $tagModel->id();
                        }
                    }

                    // Save the user if not already saved
                    $userData  = $mediaData['user'];
                    $userModel = $this->createModel('user');

                    if ($userModel->source()->tableExists()) {
                        $userModel->load($userData['id']);
                    }

                    if ($userModel->id() === null) {
                        $userModel->setData([
                            'id'     => $userData['id'],
                            'handle' => $userData['username'],
                            'name'   => $userData['full_name'],
                            'avatar' => $userData['profile_picture']
                        ]);
                        $userModel->save();
                    }

                    $mediaModel->setData([
                        'id'           => $mediaData['id'],
                        'created_date' => $time->setTimestamp($mediaData['created_time']),
                        'tags'         => $tags,
                        'caption'      => $mediaData['caption']['text'],
                        'user'         => $userModel->id(),
                        'image'        => $mediaData['images']['standard_resolution']['url'],
                        'type'         => $mediaData['type'],
                        'raw_data'     => json_encode($mediaData)
                    ]);

                    $mediaModel->save();
                }

                $posts[] = $mediaModel;
            }

            $this->setResults($posts);
        }

        return $this->results;
    }

    /**
     * Fetch media from the Instagram API.
     *
     * @param  array|null $params Scraping options.
     * @param  boolean    $force  Force a new request to the API.
     *     This will save a new scrape record.
     * @throws HitException If the Instagram was recently scraped.
     * @throws NotFoundException If the API endpoint does not exist.
     * @throws RateLimitException If the too many requests have been made.
     * @throws OAuthException If the request failed (OAuth).
     * @throws ApiException If the request failed (API).
     * @return array|null
     */
    private function fetchMediaFromApi(array $params = [], $force = false)
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
            'count'  => null,
            'max_id' => null,
            'min_id' => null
        ];
        $filters = array_replace($defaults, $params['filters']);

        if ($filters['min_id'] === null) {
            $media = $this->fetchLatestMedia();
            if ($media) {
                $filters['min_id'] = $media->id();
            }
        }

        $minId = $filters['min_id'];
        $maxId = $filters['max_id'];

        // First, attempt fetching Instagram data through pagination
        try {
            while ($callApi) {
                $response = $this->client()->{$params['repository']}()->{$params['method']}(
                    $filters['id'],
                    $filters['count'],
                    $minId,
                    $maxId
                );

                $results = $response->get()->merge($results);

                if (empty($response->pagination->next_max_tag_id)) {
                    $callApi = false;
                } else {
                    $maxId = $response->pagination->next_max_tag_id;
                }
            }
        } catch (APINotFoundError $e) {
            $message = sprintf(
                'Exception [%s]: %s',
                $class,
                $e->getMessage()
            );
            $record->setStatus($message);

            throw new NotFoundException($message, 0, $e);
        } catch (OAuthRateLimitException $e) {
            $message = sprintf(
                'Exception [%s]: %s',
                $class,
                $e->getMessage()
            );
            $record->setStatus($message);

            throw new RateLimitException($message, 0, $e);
        } catch (ElogramException $e) {
            $class   = get_class($e);
            $message = sprintf(
                'Exception [%s]: %s',
                $class,
                $e->getMessage()
            );
            $record->setStatus($message);

            if (strtolower(substr($class, 0, 5)) === 'oauth') {
                throw new OAuthException($message, 0, $e);
            }

            throw new ApiException($message, 0, $e);
        } finally {
            // Save the scrape record for caching purposes
            $record->save();
        }

        return $results;
    }

    /**
     * Attempt to get the latest media object.
     *
     * @return ModelInterface|null
     */
    public function fetchLatestMedia()
    {
        $obj = $this->createModel('media', false);
        $obj->loadFromQuery('SELECT * FROM `'.$obj->source()->table().'` ORDER BY `created_date` DESC LIMIT 1');

        if ($obj->id()) {
            return $obj;
        }

        return null;
    }
}
