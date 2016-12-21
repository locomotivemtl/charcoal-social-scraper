<?php

namespace Charcoal\SocialScraper\Object;

use DateTimeInterface;

// From 'charcoal-core'
use Charcoal\Model\AbstractModel as CharcoalModel;

// From 'charcoal-support'
use Charcoal\Support\Property\ParsableValueTrait;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Object\HashtagInterface;

/**
 * Defines Third-Party Hashtag Model
 */
abstract class AbstractHashtag extends CharcoalModel implements
    HashtagInterface
{
    use ParsableValueTrait;

    /**
     * Posts are active by default.
     *
     * @var boolean
     */
    protected $active = true;

    /**
     * Post import date (when the tag was scraped).
     *
     * @var DateTimeInterface|null
     */
    protected $importDate;

    /**
     * Determine if the tag is active.
     *
     * @return boolean
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * Set whether the tag is active or not.
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
     * Retrieve the tag's importation timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function importDate()
    {
        return $this->importDate;
    }

    /**
     * Set the tag's importation timestamp.
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
     * Event called before _creating_ the tag.
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
