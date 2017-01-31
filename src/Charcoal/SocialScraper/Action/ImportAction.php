<?php

namespace Charcoal\SocialScraper\Action;

use Exception as PhpException;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Action\AbstractScraperAction;
use Charcoal\SocialScraper\Exception\Exception as ScraperException;
use Charcoal\SocialScraper\Exception\HitException;
use Charcoal\SocialScraper\Exception\NotFoundException;
use Charcoal\SocialScraper\Exception\RateLimitException;
use Charcoal\SocialScraper\Traits\ImportableTrait;

/**
 * API: Import posts from Social Networks
 */
class ImportAction extends AbstractScraperAction
{
    use ImportableTrait;

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

        try {
            $this->setConfig($request->getParams());
        } catch (ScraperException $e) {
            $this->addFeedback('error', $e->getMessage());
            $this->updateResponseWithStatus(400);
        }

        $this->import();

        $this->setSuccess($this->isSuccessful());

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
            $request = $this->config('request');
            $sources = $this->config('scrapers');
            foreach ($sources as $ident) {
                $scraper = $this->scraper($ident);
                $results = $scraper->scrape($request);
                if ($results) {
                    $count = count($results);
                    $this->addFeedback('success', sprintf(
                        'Scraped %d %s from "%s".',
                        $count,
                        ($count === 1 ? 'item' : 'items'),
                        $scraper->label()
                    ));
                } else {
                    $this->addFeedback('notice', sprintf('Nothing new from "%s".', $scraper->label()));
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
