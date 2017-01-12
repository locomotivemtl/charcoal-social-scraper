<?php

namespace Charcoal\SocialScraper\Object;

use DateTimeInterface;

// From 'charcoal-core'
use Charcoal\Model\AbstractModel as CharcoalModel;

// From 'charcoal-support'
use Charcoal\Support\Property\ParsableValueTrait;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Object\PostInterface;

/**
 * A Basic Third-Party Post Model
 */
abstract class AbstractPost extends CharcoalModel implements
    PostInterface
{
    use ParsableValueTrait;

    /**
     * Posts are active by default.
     *
     * @var boolean
     */
    protected $active = true;

    /**
     * Post import date (when the post was scraped).
     *
     * @var DateTimeInterface|null
     */
    protected $importDate;

    /**
     * Post creation date (provided by third-party).
     *
     * @var DateTimeInterface|null
     */
    protected $createdDate;

    /**
     * The person who created the post (provided by third-party).
     *
     * @var mixed
     */
    protected $user;

    /**
     * The post's URL on the third-party service.
     *
     * @var string|null
     */
    protected $url;

    /**
     * The post's original API data structure.
     *
     * As provided by the third-party service.
     *
     * @var array|string|null
     */
    protected $rawData;

    /**
     * Determine if the post is active.
     *
     * @return boolean
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * Set whether the post is active or not.
     *
     * @param  boolean $active TRUE to enable, FALSE to disable.
     * @return AbstractPost
     */
    public function setActive($active)
    {
        $this->active = !!$active;

        return $this;
    }

    /**
     * Retrieve the post's importation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function importDate()
    {
        return $this->importDate;
    }

    /**
     * Set the post's importation timestamp.
     *
     * @param  string|DateTime $time A date/time value. Valid formats are explained in
     *     {@link http://php.net/manual/en/datetime.formats.php Date and Time Formats}.
     * @return self
     */
    public function setImportDate($time)
    {
        $this->importDate = $this->parseAsDateTime($time);

        return $this;
    }

    /**
     * Retrieve the post's creation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function createdDate()
    {
        return $this->createdDate;
    }

    /**
     * Set the post's creation timestamp.
     *
     * @param  string|DateTime $time A date/time value. Valid formats are explained in
     *     {@link http://php.net/manual/en/datetime.formats.php Date and Time Formats}.
     * @return self
     */
    public function setCreatedDate($time)
    {
        $this->createdDate = $this->parseAsDateTime($time);

        return $this;
    }

    /**
     * Retrieve the person who created the post.
     *
     * @return mixed
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Set the person who created the post.
     *
     * @param  mixed $user A user.
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Retrieve the post's URL on the third-party service.
     *
     * @return string|null
     */
    public function url()
    {
        return $this->url;
    }

    /**
     * Set the post's URL on the third-party service.
     *
     * @param  string $url The post's URL.
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Retrieve the post's original data structure from the API.
     *
     * @return array|null
     */
    public function rawData()
    {
        return $this->rawData;
    }

    /**
     * Set the post's original data structure from the API.
     *
     * @param  array|string $data A JSON structure.
     * @return self
     */
    public function setRawData($data)
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        $this->rawData = $data;

        return $this;
    }

    /**
     * Event called before _creating_ the post.
     *
     * @see    Charcoal\Source\StorableTrait::preSave() For the "create" Event.
     * @return boolean
     */
    public function preSave()
    {
        $this->setImportDate('now');

        return parent::preSave();
    }
}
