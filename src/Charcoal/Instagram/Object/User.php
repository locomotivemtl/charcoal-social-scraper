<?php

namespace Charcoal\Instagram\Object;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Object\AbstractUser;

/**
 * Instagram User Object
 */
class User extends AbstractUser
{
    /**
     * The base URI to an Instagram user profile.
     *
     * @const string
     */
    const URL_PATTERN = 'https://www.instagram.com/%handle';

    /**
     * Simple concat of @ and handle.
     *
     * @return string
     */
    public function via()
    {
        return '@'.$this->handle();
    }

    /**
     * Retrieve the user's URL on the third-party service.
     *
     * @return string
     */
    public function url()
    {
        return strtr(self::URL_PATTERN, [
            '%id'     => $this->id(),
            '%handle' => $this->handle(),
        ]);
    }
}
