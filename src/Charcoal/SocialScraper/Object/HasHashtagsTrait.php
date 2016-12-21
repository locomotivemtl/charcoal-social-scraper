<?php

namespace Charcoal\SocialScraper\Object;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Object\HashtagInterface;

/**
 * An implementation, as Trait, of the {@see \Charcoal\SocialScraper\Object\HasHashtagsInterface}.
 *
 * @uses \Charcoal\Support\Property\ParsableValueTrait
 */
trait HasHashtagsTrait
{
    /**
     * One or more tags associated with the post (provided by third-party).
     *
     * @var HashtagInterface[]|array|null
     */
    protected $tags;

    /**
     * Retrieve the tags associated with the post.
     *
     * @return HashtagInterface[]|array|null
     */
    public function tags()
    {
        return $this->tags;
    }

    /**
     * Set the tags associated with the post.
     *
     * @param  HashtagInterface[]|array|null $tags Hashtags.
     * @return self
     */
    public function setTags($tags)
    {
        $this->tags = $this->parseAsMultiple($tags);

        return $this;
    }
}
