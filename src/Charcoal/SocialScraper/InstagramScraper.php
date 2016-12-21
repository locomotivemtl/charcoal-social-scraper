<?php

namespace Charcoal\SocialScraper;

use DateTime;
use Exception;
use RuntimeException;
use InvalidArgumentException;

// From 'larabros/elogram'
use Larabros\Elogram\Client as InstagramClient;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Exception\ApiResponseException;
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
    const NETWORK = 'instagram';

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
     * @param  InstagramClient $client The client used to query the API.
     * @return self
     */
    public function setClient(InstagramClient $client)
    {
        $this->client = $client;

        return $this;
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
     * Scrape Instagram API and parse scraped data to create Charcoal models.
     *
     * @param  array $options Scraping options.
     * @throws Exception If something goes wrong with API calls.
     * @return ModelInterface[]|null
     */
    private function scrapeMedia(array $options = [])
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
            $max       = null;
            $min       = null;
            $rawMedias = [];
            $models    = [];

            $defaults  = [
                'count' => 32
            ];
            $immutable = [];

            $filters = array_replace_recursive($defaults, $options['filters'], $immutable);

            // First, attempt fetching Instagram data through pagination
            try {
                while ($callApi) {
                    $apiResponse = $this->client()->{$options['repository']}()->{$options['method']}(
                        $filters['id'],
                        $filters['count'],
                        $min,
                        $max
                    );

                    $rawMedias = $apiResponse->get()->merge($rawMedias);

                    if (empty($apiResponse->pagination->next_max_tag_id)) {
                        $callApi = false;
                    } else {
                        $max = $apiResponse->pagination->next_max_tag_id;
                    }
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
            foreach ($rawMedias as $media) {
                $mediaModel = $this->modelFactory()->create(Media::class);

                if (!$mediaModel->source()->tableExists()) {
                    $mediaModel->source()->createTable();
                }

                $mediaModel->load($media['id']);

                if ($mediaModel->id() === null) {
                    $tags = [];

                    foreach ($media['tags'] as $tag) {
                        // Save the hashtags if not already saved
                        $tagModel = $this->modelFactory()->create(Tag::class);

                        if (!$tagModel->source()->tableExists()) {
                            $tagModel->source()->createTable();
                        }

                        $tagModel->load($tag);

                        if ($tagModel->id() === null) {
                            $tagModel->setData([
                                'id' => $tag
                            ]);
                            $tagModel->save();
                        }

                        $tags[] = $tagModel->id();
                    }

                    // Save the user if not already saved
                    $userData = $media['user'];
                    $userModel = $this->modelFactory()->create(User::class);

                    if (!$userModel->source()->tableExists()) {
                        $userModel->source()->createTable();
                    }

                    $userModel->load($userData['id']);

                    if ($userModel->id() === null) {
                        $userModel->setData([
                            'id'             => $userData['id'],
                            'username'       => $userData['username'],
                            'fullName'       => $userData['full_name'],
                            'profilePicture' => $userData['profile_picture']
                        ]);
                        $userModel->save();
                    }

                    $created = new DateTime('now');
                    $created->setTimestamp($media['created_time']);

                    $mediaModel->setData([
                        'id'      => $media['id'],
                        'created' => $created,
                        'tags'    => $tags,
                        'caption' => $media['caption']['text'],
                        'user'    => $userModel->id(),
                        'image'   => $media['images']['standard_resolution']['url'],
                        'type'    => $media['type'],
                        'json'    => json_encode($media)
                    ]);

                    $mediaModel->save();
                }

                $models[] = $mediaModel;
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
