<?php

namespace Charcoal\Twitter\Object;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Object\AbstractUser;

/**
 * Twitter User Object
 */
class User extends AbstractUser
{
    /**
     * The base URI to a Twitter user profile.
     *
     * @const string
     */
    const URL_PATTERN = 'https://www.twitter.com/%handle';

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
