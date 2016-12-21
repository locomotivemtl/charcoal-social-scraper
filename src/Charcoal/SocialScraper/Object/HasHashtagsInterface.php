<?php

namespace Charcoal\SocialScraper\Object;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Object\HashtagInterface;

/**
 * Defines an object that can be associated to {@link https://en.m.wikipedia.org/wiki/Hashtag hashtags}.
 *
 * Implementation, as trait, provided by {@see \Charcoal\SocialScraper\Object\HasHashtagsTrait}.
 */
interface HasHashtagsInterface
{
    /**
     * Retrieve the tags associated with the post.
     *
     * @return HashtagInterface[]|array|null
     */
    public function tags();
}
