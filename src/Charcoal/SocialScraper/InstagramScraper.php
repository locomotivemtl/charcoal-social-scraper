<?php

namespace Charcoal\SocialScraper;

use \DateTime;
use \Exception;
use \InvalidArgumentException;

// From 'larabros/elogram'
use \Larabros\Elogram\Client as InstagramClient;

// From `charcoal-social-scraper`
use \Charcoal\Instagram\Object\Media;
use \Charcoal\Instagram\Object\Tag;
use \Charcoal\Instagram\Object\User;
use \Charcoal\SocialScraper\AbstractScraper;

/**
 * Scraping class that connects to Instagram API and converts data to Charcoal Objects.
 */
class InstagramScraper extends AbstractScraper implements
    ScraperInterface
{
    /**
     * @var array $results
     */
    private $results;

    /**
     * @param InstagramClient $client The Instagram Client used to query the API.
     * @return self
     */
    private function setClient(InstagramClient $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Retrieve the Instagram Client.
     *
     * @throws Exception If the Instagram client was not properly set.
     * @return InstagramClient
     */
    protected function client()
    {
        if ($this->client === null) {
            throw new Exception(
                'Can not access Instagram client, the dependency has not been set.'
            );
        }
        return $this->client;
    }

    /**
     * Retrieve results.
     *
     * @return ModelInterface[]|array|null
     */
    public function results()
    {
        return $this->results;
    }

    /**
     * Scrape Instagram API according to hashtag.
     *
     * @param  string $tag The searched tag.
     * @throws InvalidArgumentException If the query is not a string.
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

        return $this->scrapeMedia([
            'record' => [
                'source' => 'instagram',
                'repository' => 'tags',
                'method' => 'getRecentMedia',
                'filter' => $tag
            ]
        ]);
    }

    /**
     * Scrape Instagram API for all posts by authorized user.
     *
     * @return ModelInterface[]|null
     */
    public function scrapeAll()
    {
        return $this->scrapeMedia([
            'record' => [
                'source' => 'instagram',
                'repository' => 'users',
                'method' => 'getMedia',
                'filter' => 'self'
            ]
        ]);
    }

    /**
     * Scrape Instagram API and parse scraped data to create Charcoal models.
     *
     * @param  string $tag The searched tag.
     * @param  array  $data  Raw API data.
     * @throws InvalidArgumentException If the query is not a string.
     * @return ModelInterface[]|null
     */
    private function scrapeMedia(array $options = [])
    {
        if ($this->results === null) {
            // Test for recent scrapes
            $record = $this->fetchRecentScrapeRecord($options['record']);

            // An non-null ID means a recent record exists
            if ($record->id() !== null) {
                return $this->results;
            }

            // Reset results
            $this->results = [];

            $callApi = true;
            $max = $min = null;
            $rawMedias = [];
            $models = [];

            // First, attempt fetching Instagram data through pagination
            try {
                while ($callApi) {
                    $apiResponse = $this->instagramClient()->{$options['repository']}()->{$options['method']}($options['filter'], 32, $min, $max);

                    $rawMedias = $apiResponse->get()->merge($rawMedias);

                    if (empty($apiResponse->pagination->next_max_tag_id)) {
                        $callApi = false;
                    } else {
                        $max = $apiResponse->pagination->next_max_tag_id;
                    }
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
            foreach ($rawMedias as $media) {
                $mediaModel = $this->modelFactory()->create(Media::class);

                if (!$mediaModel->source()->tableExists()) {
                    $mediaModel->source()->createTable();
                }

                $mediaModel->load($media['id']);

                if ($mediaModel->id() === null) {
                    $tags = [];

                    foreach($media['tags'] as $tag) {
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
                            'id' => $userData['id'],
                            'username' => $userData['username'],
                            'fullName' => $userData['full_name'],
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

            $this->results = $models;

        }

        return $this->results;
    }
}
