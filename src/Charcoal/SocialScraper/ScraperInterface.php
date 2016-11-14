<?php

namespace Charcoal\SocialScraper;

/**
 * Defines a social media scraper.
 */
interface ScraperInterface
{
    /**
     * Set a social media client.
     *
     * @param mixed $client The client instance, to query an API.
     * @return self
     */
    public function setClient($client);

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
}
