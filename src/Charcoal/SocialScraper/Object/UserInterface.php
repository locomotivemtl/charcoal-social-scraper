<?php

namespace Charcoal\SocialScraper\Object;

use DateTimeInterface;

/**
 * Defines Third-Party User Model
 */
interface UserInterface
{
    /**
     * Determine if the user is active.
     *
     * @return boolean
     */
    public function active();

    /**
     * Retrieve the user's importation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function importDate();

    /**
     * Retrieve the user's creation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function createdDate();

    /**
     * Retrieve the user's screen name on the third-party service.
     *
     * @return string|null
     */
    public function handle();

    /**
     * Retrieve the user's profile name on the third-party service.
     *
     * @return string|null
     */
    public function name();

    /**
     * Retrieve the URL to the post's profile picture.
     *
     * @return string
     */
    public function avatar();

    /**
     * Retrieve the user's URL on the third-party service.
     *
     * @return string|null
     */
    public function url();
}
