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
    protected function setClient($client);

    /**
     * Retrieve the social media client.
     *
     * @return mixed
     */
    public function client();
}
