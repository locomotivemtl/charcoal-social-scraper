<?php

namespace Charcoal\Twitter\Object;

// From Pimple
use Pimple\Container;

// From 'charcoal-support'
use Charcoal\Support\Model\ManufacturableModelTrait;
use Charcoal\Support\Model\ManufacturableModelCollectionTrait;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Object\AbstractPost;
use Charcoal\SocialScraper\Object\HasHashtagsInterface;
use Charcoal\SocialScraper\Object\HasHashtagsTrait;

use Charcoal\Twitter\Object\Tag;
use Charcoal\Twitter\Object\User;

/**
 * Twitter "{@link https://dev.twitter.com/overview/api/tweets Tweet}" Object
 */
class Tweet extends AbstractPost implements
    HasHashtagsInterface
{
    use HasHashtagsTrait;
    use ManufacturableModelTrait;
    use ManufacturableModelCollectionTrait;

    /**
     * The base URI to a Twitter user profile.
     *
     * @const string
     */
    const URL_PATTERN = 'https://www.twitter.com/%handle/status/%id';

    /**
     * The "photo" media type.
     *
     * @const string
     */
    const ENTITY_TYPE_PHOTO = 'photo';

    /**
     * The text of the tweet (provided by third-party).
     *
     * @var string|null
     */
    protected $text;

    /**
     * The extracted URLs from the post.
     *
     * @var array|null
     */
    protected $urls;

    /**
     * The extracted images from the post.
     *
     * @var array|null
     */
    protected $images;

    /**
     * Inject dependencies from a DI Container.
     *
     * @param Container $container A dependencies container instance.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setCollectionLoader($container['model/collection/loader']);
        $this->setCollectionLoaderFactory($container['model/collection/loader/factory']);
        $this->setModelFactory($container['model/factory']);
    }

    /**
     * Retrieve the post's user.
     *
     * @return ModelInterface|null
     */
    public function user()
    {
        if ($this->user && !($this->user instanceof User)) {
            $this->user = $this->castTo($this->user, User::class);
        }

        return $this->user;
    }

    /**
     * Retrieve the post's message.
     *
     * @return string|null
     */
    public function text()
    {
        return $this->text;
    }

    /**
     * Set the post's message.
     *
     * @param  string $text The status update.
     * @return self
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Retrieve the post's URL on the third-party service.
     *
     * @return string
     */
    public function url()
    {
        return strtr(self::URL_PATTERN, [
            '%id'     => $this->id(),
            '%handle' => $this->user()->handle(),
        ]);
    }

    /**
     * Retrieve the extracted URLs from the post.
     *
     * @return array
     */
    public function urls()
    {
        if ($this->urls === null) {
            $data = $obj->rawData();
            $urls = [];
            if (isset($data['entities']['urls'])) {
                foreach ($data['entities']['urls'] as $media) {
                    if (!isset($media['type']) || $media['type'] !== self::ENTITY_TYPE_PHOTO) {
                        continue;
                    }

                    if (isset($media['expanded_url'])) {
                        $urls[] = $media['expanded_url'];
                    } elseif (isset($media['url'])) {
                        $urls[] = $media['url'];
                    }
                }
            }

            $this->urls = $urls;
        }

        return $this->urls;
    }

    /**
     * Retrieve the extracted images from the post.
     *
     * @return array
     */
    public function images()
    {
        if ($this->images === null) {
            $data   = $this->rawData();
            $images = [];
            if (isset($data['entities']['media'])) {
                foreach ($data['entities']['media'] as $media) {
                    if (!isset($media['type']) || $media['type'] !== self::ENTITY_TYPE_PHOTO) {
                        continue;
                    }

                    if (!isset($media['media_url_https'])) {
                        continue;
                    }

                    $images[] = $media['media_url_https'];
                }
            }

            $this->images = $images;
        }

        return $this->images;
    }

    /**
     * Retrieve the primary image extracted from the post.
     *
     * @return string|null
     */
    public function image()
    {
        $images = $this->images();

        if ($images) {
            return reset($images);
        }

        return null;
    }



    // Utilities
    // =============================================================================================

    /**
     * Function that will turn all HTTP URLs, Twitter @usernames, and #tags into links.
     *
     * @link   https://davidwalsh.name/linkify-twitter-feed
     * @param  string $text A tweet's message.
     * @return string
     */
    public function linkifyTweet($text)
    {
        // Linkify URLs
        $text = preg_replace(
            '/(https?:\/\/\S+)/',
            '<a target="_blank" href="\1">\1</a>',
            $text
        );

        // Linkify twitter users
        $text = preg_replace(
            '/(^|\s)@(\w+)/',
            '\1@<a target="_blank" href="https://twitter.com/\2">\2</a>',
            $text
        );

        // Linkify tags
        $text = preg_replace(
            '/(^|\s)#(\w+)/',
            '\1#<a target="_blank" href="https://search.twitter.com/search?q=%23\2">\2</a>',
            $text
        );

        return $text;
    }



    // Events
    // =============================================================================================

    /**
     * Event called before _creating_ the post.
     *
     * @see    \Charcoal\Source\StorableTrait::preSave() For the "create" Event.
     * @return boolean
     */
    public function preSave()
    {
        $this->setText($this->linkifyTweet($this->text()));

        return parent::preSave();
    }

    /**
     * Event called before _updating_ the post.
     *
     * @see    \Charcoal\Source\StorableTrait::postUpdate() For the "update" Event.
     * @see    \Charcoal\Object\RoutableTrait::generateObjectRoute()
     * @param  array $properties Optional. The list of properties to update.
     * @return boolean
     */
    public function preUpdate(array $properties = null)
    {
        $this->setText($this->linkifyTwitterStatus($this->text()));

        return parent::preUpdate($properties);
    }
}
