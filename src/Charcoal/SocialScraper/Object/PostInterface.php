<?php

namespace Charcoal\SocialScraper\Object;

use DateTimeInterface;

/**
 * Defines Third-Party Post Model
 */
interface PostInterface
{
    /**
     * Determine if the post is active.
     *
     * @return boolean
     */
    public function active();

    /**
     * Retrieve the post's importation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function importDate();

    /**
     * Retrieve the post's creation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function createdDate();

    /**
     * Retrieve the person who created the post.
     *
     * @return mixed
     */
    public function user();

    /**
     * Retrieve the post's URL on the third-party service.
     *
     * @return string|null
     */
    public function url();

    /**
     * Retrieve the post's original data structure from the API.
     *
     * As provided by the third-party service.
     *
     * @return array|null
     */
    public function rawData();
}
