<?php

namespace Charcoal\SocialScraper\Action;

use Exception as PhpException;
use OutOfBoundsException;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From Pimple
use Pimple\Container;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Action\AbstractScraperAction;
use Charcoal\SocialScraper\Exception\Exception as ScraperException;
use Charcoal\SocialScraper\Exception\ApiException;
use Charcoal\SocialScraper\Exception\HitException;
use Charcoal\SocialScraper\Exception\NotFoundException;
use Charcoal\SocialScraper\Exception\RateLimitException;
use Charcoal\SocialScraper\Traits\ScraperAwareTrait;
use Charcoal\SocialScraper\ScraperInterface;

/**
 * Import posts from Social Networks
 */
class ImportAction extends AbstractScraperAction
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected $defaultConfig = [
        'scrapers'    => [],
        'request'     => null,
        'count'       => null,
        'tags'        => [],
        'user_id'     => null,
        'screen_name' => null,
    ];

    /**
     * Execute the endpoint.
     *
     * @param  RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param  ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $this->setHttpRequest($request);
        $this->setHttpResponse($response);

        $this->setConfig($request->getParams());

        $this->import();

        if ($this->isSuccessful()) {
            $this->setSuccess(true);
        } else {
            $this->setSuccess(false);
        }

        return $this->httpResponse();
    }

    /**
     * Import Posts
     *
     * @todo   Add support for 'user_id', 'screen_name', 'count', 'min_id', 'max_id'.
     * @return self
     */
    public function import()
    {
        try {
            $request  = $this->config('request');
            $scrapers = $this->config('scrapers');
            foreach ($scrapers as $ident) {
                $results = $this->scraper($ident)->scrape($request);
                if ($results) {
                    $count = count($results);
                    $this->addFeedback('success', sprintf(
                        'Scraped %d %s from "%s".',
                        $count,
                        ($count === 1 ? 'item' : 'items'),
                        $ident
                    ));
                } else {
                    $this->addFeedback('notice', sprintf('Nothing new from "%s".', $ident));
                }
            }
        } catch (HitException $e) {
            $this->addFeedback('notice', $e->getMessage());
            /** @todo Maybe 304 or 420? */
            $this->updateResponseWithStatus(429);
        } catch (RateLimitException $e) {
            $this->addFeedback('warning', $e->getMessage());
            $this->updateResponseWithStatus(429);
        } catch (NotFoundException $e) {
            $this->addFeedback('error', $e->getMessage());
            $this->updateResponseWithStatus(404);
        } catch (ScraperException $e) {
            $this->addFeedback('error', $e->getMessage());
            if ($this->isSuccessful()) {
                $this->updateResponseWithStatus(500);
            }
        } catch (PhpException $e) {
            $this->addFeedback('error', $e->getMessage());
            if ($this->isSuccessful()) {
                $this->updateResponseWithStatus(500);
            }
        }

        return $this;
    }

    /**
     * @param  mixed  $scrapers One or more scrapers.
     * @param  string $key      The confiset's data key.
     * @return ScraperInterface[]
     */
    public function parseScrapers($scrapers, &$key)
    {
        $key = 'scrapers';

        $available = $this->availableScrapers();
        $choices   = $scrapers;
        $scrapers  = [];
        if (!empty($choices)) {
            if (!is_array($choices)) {
                $choices = explode(',', $choices);
            }

            $scrapers = array_intersect($available, array_unique($choices));
        }

        if (!$scrapers) {
            $this->addFeedback('error', sprintf(
                'Missing scraper(s). Available scrapers are: "%s".',
                implode('", "', $available)
            ));
            $this->updateResponseWithStatus(400);
        }

        return $scrapers;
    }

    /**
     * Alias of {@see self::parseScrapers()}.
     *
     * @param  mixed  $scrapers One or more scrapers to be scraped.
     * @param  string $key      The confiset's data key.
     * @return ScraperInterface[]
     */
    public function parseScraper($scrapers, &$key)
    {
        return $this->parseScrapers($scrapers, $key);
    }

    /**
     * @param  mixed  $tags One or more "hashtags" to filter posts by.
     * @param  string $key  The confiset's data key.
     * @return string[]
     */
    public function parseTags($tags, &$key = null)
    {
        $key = 'tags';

        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }

        foreach ($tags as &$tag) {
            if (0 === strpos($tag, '#')) {
                $tag = substr($tag, 1);
            }
        }

        return $tags;
    }

    /**
     * Alias of {@see self::parseTags()}.
     *
     * @param  mixed  $tags One or more "hashtags" to filter posts by.
     * @param  string $key  The confiset's data key.
     * @return string[]
     */
    public function parseTag($tags, &$key)
    {
        return $this->parseTags($tags, $key);
    }

    /**
     * @return array
     */
    public function results()
    {
        return [
            'success'   => $this->success(),
            'feedbacks' => $this->feedbacks()
        ];
    }
}
