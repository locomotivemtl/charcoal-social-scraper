<?php

namespace Charcoal\SocialScraper\Object;

use DateTimeInterface;

/**
 * Defines Third-Party Hashtag Model
 */
interface HashtagInterface
{
    /**
     * Determine if the tag is active.
     *
     * @return boolean
     */
    public function active();

    /**
     * Retrieve the tag's importation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function importDate();
}
