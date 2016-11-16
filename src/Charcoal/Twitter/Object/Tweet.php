<?php

namespace Charcoal\Twitter\Object;

use \DateTime;
use \DateTimeInterface;
use \InvalidArgumentException;

// From `pimple`
use \Pimple\Container;

// From `charcoal-factory`
use \Charcoal\Factory\FactoryInterface;

// From `charcoal-core`
use \Charcoal\Model\AbstractModel;

// From `charcoal-support`
use \Charcoal\Support\Container\DependentInterface;
use \Charcoal\Support\Model\ManufacturableModelTrait;
use \Charcoal\Support\Model\ManufacturableModelCollectionTrait;
use \Charcoal\Support\Property\ParsableValueTrait;

// From `charcoal-social-scraper`
use \Charcoal\Twitter\Object\Tag;
use \Charcoal\Twitter\Object\User;

/**
 * Twitter Tweet Object
 */
class Tweet extends AbstractModel implements
    DependentInterface
{
    use ManufacturableModelTrait;
    use ManufacturableModelCollectionTrait;
    use ParsableValueTrait;

    /**
     * Objects are active by default.
     *
     * @var boolean $active
     */
    protected $active = true;

    /**
     * Object creation date (provided by third-party).
     *
     * @var DateTime $created
     */
    protected $created;

    /**
     * One or more tags the object belongs to (provided by third-party).
     *
     * @var ModelInterface[]|array|null
     */
    protected $tags;

    /**
     * The object's content (provided by third-party).
     *
     * @var string|null
     */
    protected $content;

    /**
     * User object that created the media (provided by third-party).
     *
     * @var ModelInterface|null
     */
    protected $user;

    /**
     * The object's JSON representation/backup as provided by third-party when saved.
     *
     * @var string|null
     */
    protected $json;

    /**
     * @const URL_USER  The base URI to a Twitter user profile.
     */
    const URL_USER  = 'https://www.twitter.com/';

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
     * Function that will turn all HTTP URLs, Twitter @usernames, and #tags into links.
     *
     * @see  https://davidwalsh.name/linkify-twitter-feed
     * @param  string $text Tweet object->text
     * @return string
     */
    public function linkifyTwitterStatus($content)
    {
        // Linkify URLs
        $content = preg_replace(
            '/(https?:\/\/\S+)/',
            '<a target="_blank" href="\1">\1</a>',
            $content
        );

        // Linkify twitter users
        $content = preg_replace(
            '/(^|\s)@(\w+)/',
            '\1@<a target="_blank" href="https://twitter.com/\2">\2</a>',
            $content
        );

        // Linkify tags
        $content = preg_replace(
            '/(^|\s)#(\w+)/',
            '\1#<a target="_blank" href="https://search.twitter.com/search?q=%23\2">\2</a>',
            $content
        );

        return $content;
    }

    // Setters and getters
    // =================================================================================================================

    /**
     * @param boolean $active The active flag.
     * @return Content Chainable
     */
    public function setActive($active)
    {
        $this->active = !!$active;

        return $this;
    }

    /**
     * @return boolean
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * Set the object's creation date.
     *
     * @param \DateTimeInterface|string|null $created The date/time at object's creation.
     * @throws InvalidArgumentException If the date/time is invalid.
     * @return self
     */
    public function setCreated($created)
    {
        if ($created === null) {
            $this->created = null;
            return $this;
        }
        if (is_string($created)) {
            $created = new DateTime($created);
        }
        if (!($created instanceof DateTimeInterface)) {
            throw new InvalidArgumentException(
                'Invalid "Created" value. Must be a date/time string or a DateTime object.'
            );
        }
        $this->created = $created;

        return $this;
    }

    /**
     * Retrieve the object's creation date.
     *
     * @return DateTimeInterface|null
     */
    public function created()
    {
        return $this->created;
    }

    /**
     * Set the tags the object belongs to.
     *
     * @param ModelInterface[]|array|null $tags The object's tags.
     * @return self
     */
    public function setTags($tags)
    {
        $this->tags = $this->parseAsMultiple($tags);

        return $this;
    }

    /**
     * Retrieve the tags the object belongs to.
     *
     * @return ModelInterface[]|array|null
     */
    public function tags()
    {
        return $this->tags;
    }

    /**
     * Set the object's content.
     *
     * @param  string|null $content The content.
     * @return self
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Retrieve the object's content.
     *
     * @return string|null
     */
    public function content()
    {
        return $this->content;
    }

    /**
     * Set the object's user.
     *
     * @param ModelInterface|null
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;
        $this->user = $this->castTo($user, User::class);

        return $this;
    }

    /**
     * Retrieve the object's user.
     *
     * @return ModelInterface|null
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Set the object's original JSON structure.
     *
     * @param string $json A JSON structure.
     * @return self
     */
    public function setJson($json)
    {
        $this->json = $json;

        return $this;
    }

    /**
     * Retrieve the object's JSON structure.
     *
     * @return string
     */
    public function json()
    {
        return $this->json;
    }

    /**
     * Retrieve the object's URL
     *
     * @return string
     */
    public function url()
    {
        return self::URL_USER . $this->user()->handle() . '/status/' . $this->id();
    }

    // Events
    // =================================================================================================================

    /**
     * Event called before _creating_ the object.
     *
     * @see    \Charcoal\Source\StorableTrait::preSave() For the "create" Event.
     * @return boolean
     */
    public function preSave()
    {
        $this->setContent($this->linkifyTwitterStatus($this->content()));

        return parent::preSave();
    }

    /**
     * Event called before _updating_ the object.
     *
     * @see    \Charcoal\Source\StorableTrait::postUpdate() For the "update" Event.
     * @see    \Charcoal\Object\RoutableTrait::generateObjectRoute()
     * @param  array $properties Optional. The list of properties to update.
     * @return boolean
     */
    public function preUpdate(array $properties = null)
    {
        $this->setContent($this->linkifyTwitterStatus($this->content()));

        return parent::preUpdate($properties);
    }
}
