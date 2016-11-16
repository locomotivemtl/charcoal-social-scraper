<?php

namespace Charcoal\Twitter\Object;

use \DateTime;
use \DateTimeInterface;
use \InvalidArgumentException;

// From `charcoal-core`
use \Charcoal\Model\AbstractModel;

/**
 * Twitter Tag Object
 */
class User extends AbstractModel
{
    /**
     * Objects are active by default.
     *
     * @var boolean $active
     */
    protected $active = true;

    /**
     * The object's name.
     *
     * @var string|null
     */
    protected $name;

    /**
     * The object's handle.
     *
     * @var string|null
     */
    protected $handle;

    /**
     * The path to the object's profile picture.
     *
     * @var string|null
     */
    protected $profilePicture;

    /**
     * @const URL_USER  The base URI to a Twitter user profile.
     */
    const URL_USER  = 'https://www.twitter.com/';

    /**
     * Simple concat of @ and handle
     *
     * @return string
     */
    public function via()
    {
        return '@'.$this->handle();
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
     * Retrieve the object's name.
     *
     * @param string $name The displayed name.
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Retrieve the object's name.
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Retrieve the object's handle.
     *
     * @param string $handle A handle.
     * @return self
     */
    public function setHandle($handle)
    {
        $this->handle = $handle;

        return $this;
    }

    /**
     * Retrieve the object's handle.
     *
     * @return string
     */
    public function handle()
    {
        return $this->handle;
    }

    /**
     * Retrieve the object's profile picture.
     *
     * @param string $profilePicture A path to an image.
     * @return self
     */
    public function setprofilePicture($profilePicture)
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    /**
     * Retrieve the object's profile picture.
     *
     * @return string
     */
    public function profilePicture()
    {
        return $this->profilePicture;
    }

    /**
     * Retrieve the object's URL
     *
     * @return string
     */
    public function url()
    {
        return self::URL_USER . $this->handle();
    }
}
