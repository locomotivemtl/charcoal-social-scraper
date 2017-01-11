<?php

namespace Charcoal\SocialScraper;

/**
 * Defines a social media scraper.
 */
interface ScraperInterface
{
    /**
     * Retrieve the social media client.
     *
     * @return mixed
     */
    public function client();

    /**
     * Retrieve the social media network.
     *
     * @return string
     */
    public function network();

    /**
     * Retrieve results.
     *
     * @return ModelInterface[]|array|null
     */
    public function results();
}
