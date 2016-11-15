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
 * Scraping class that connects to Instagram API and converts medias/users/tags to Charcoal Objects.
 */
class InstagramScraper extends AbstractScraper implements
    ScraperInterface
{
    /**
     * The social media network. Used by ScrapeRecord
     *
     * @var string
     */
    private $network = 'instagram';

    /**
     * @param InstagramClient $client The Instagram Client used to query the API.
     * @throws Exception If the supplied client is not a proper InstagramClient.
     * @return self
     */
    public function setClient($client)
    {
        if (!$client instanceof InstagramClient) {
            throw new Exception(
                'The client must be an instance of \Larabros\Elogram\Client.'
            );
        }

        $this->client = $client;

        return $this;
    }

    /**
     * Retrieve the Instagram Client.
     *
     * @throws Exception If the Instagram client was not properly set.
     * @return InstagramClient
     */
    public function client()
    {
        if ($this->client === null) {
            throw new Exception(
                'Can not access Instagram client, the dependency has not been set.'
            );
        }
        return $this->client;
    }

    /**
     * Scrape Instagram API according to hashtag.
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

        return $this->scrapeMedia([
            'repository' => 'tags',
            'method' => 'getRecentMedia',
            'filters' => [
                'tag' => $tag
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
            'repository' => 'users',
            'method' => 'getMedia',
            'filters' => [
                'id' => 'self'
            ]
        ]);
    }

    /**
     * Scrape Instagram API and parse scraped data to create Charcoal models.
     *
     * @param  array  $options  Scraping options.
     * @throws Exception If something goes wrong with API calls.
     * @return ModelInterface[]|null
     */
    private function scrapeMedia(array $options = [])
    {
        if ($this->results === null) {
            //@todo This seems clumsy.
            $config = [ 'recordOptions' => $options ];
            $config['recordOptions']['network'] = $this->network();
            $this->setConfig($config);

            // Test for recent scrapes
            $record = $this->fetchRecentScrapeRecord();

            // An non-null ID means a recent record exists
            if ($record->id() !== null) {
                return $this->results;
            }

            $callApi = true;
            $max = $min = null;
            $rawMedias = [];
            $models = [];

            $filters = $options['filters'];

            // First, attempt fetching Instagram data through pagination
            try {
                while ($callApi) {
                    $apiResponse = $this->client()->{$options['repository']}()->{$options['method']}(current($filters), 32, $min, $max);

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
