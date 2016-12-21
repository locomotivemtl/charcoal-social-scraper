<?php

namespace Charcoal\SocialScraper\Object;

use DateTimeInterface;

// From 'charcoal-core'
use Charcoal\Model\AbstractModel as CharcoalModel;

// From 'charcoal-support'
use Charcoal\Support\Property\ParsableValueTrait;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Object\UserInterface;

/**
 * A Basic Third-Party User Model
 */
abstract class AbstractUser extends CharcoalModel implements
    UserInterface
{
    use ParsableValueTrait;

    /**
     * Users are active by default.
     *
     * @var boolean
     */
    protected $active = true;

    /**
     * User import date (when the user was scraped).
     *
     * @var DateTimeInterface|null
     */
    protected $importDate;

    /**
     * User creation date (provided by third-party).
     *
     * @var DateTimeInterface|null
     */
    protected $createdDate;

    /**
     * The user's profile name on the third-party service.
     *
     * @var string|null
     */
    protected $name;

    /**
     * The user's screen name on the third-party service.
     *
     * @var string|null
     */
    protected $handle;

    /**
     * The user's profile picture.
     *
     * @var string|null
     */
    protected $avatar;

    /**
     * The user's URL on the third-party service.
     *
     * @var string|null
     */
    protected $url;

    /**
     * Determine if the user is active.
     *
     * @return boolean
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * Set whether the user is active or not.
     *
     * @param  boolean $active TRUE to enable, FALSE to disable.
     * @return AbstractUser
     */
    public function setActive($active)
    {
        $this->active = !!$active;

        return $this;
    }

    /**
     * Retrieve the user's importation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function importDate()
    {
        return $this->importDate;
    }

    /**
     * Set the user's importation timestamp.
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
     * Retrieve the user's creation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function createdDate()
    {
        return $this->createdDate;
    }

    /**
     * Set the user's creation timestamp.
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
     * Retrieve the user's screen name on the third-party service.
     *
     * @return string|null
     */
    public function handle()
    {
        return $this->handle;
    }

    /**
     * Set the user's screen name on the third-party service.
     *
     * @param  string $name The user's screen/login name.
     * @return self
     */
    public function setHandle($name)
    {
        $this->handle = $name;

        return $this;
    }

    /**
     * Retrieve the user's profile name on the third-party service.
     *
     * @return string|null
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Set the user's profile name on the third-party service.
     *
     * @param  string $name The user's name.
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Retrieve the URL to the post's profile picture.
     *
     * @return string
     */
    public function avatar()
    {
        return $this->avatar;
    }

    /**
     * Retrieve the URL to the post's profile picture.
     *
     * @param  string $avatar A picture.
     * @return self
     */
    public function setAvatar($avatar)
    {
        $this->avatar = $avatar;

        return $this;
    }

    /**
     * Retrieve the user's URL on the third-party service.
     *
     * @return string|null
     */
    public function url()
    {
        return $this->url;
    }

    /**
     * Set the user's URL on the third-party service.
     *
     * @param  string $url The user's user.
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Event called before _creating_ the user.
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
