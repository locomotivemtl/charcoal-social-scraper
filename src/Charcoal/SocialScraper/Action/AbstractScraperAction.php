<?php

namespace Charcoal\SocialScraper\Action;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From Pimple
use Pimple\Container;

// From 'charcoal-admin'
use Charcoal\Admin\AdminAction;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Traits\ConfigurableTrait;
use Charcoal\SocialScraper\Traits\ScraperAwareTrait;

/**
 * Basic Scraper Action
 */
abstract class AbstractScraperAction extends AdminAction
{
    use ConfigurableTrait;
    use ScraperAwareTrait;

    /**
     * Store the HTTP request object.
     *
     * @var RequestInterface
     */
    protected $httpRequest;

    /**
     * Store the HTTP response object.
     *
     * @var ResponseInterface
     */
    protected $httpResponse;

    /**
     * Inject dependencies from a DI Container.
     *
     * @param  Container $container A dependencies container instance.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setScrapers($container['charcoal/social/scrapers']);
    }

    /**
     * Retrieve the HTTP request.
     *
     * @throws RuntimeException If the HTTP request was not previously set.
     * @return RequestInterface
     */
    public function httpRequest()
    {
        if (!isset($this->httpRequest)) {
            throw new RuntimeException(
                sprintf('HTTP Request is not defined for "%s"', get_class($this))
            );
        }

        return $this->httpRequest;
    }

    /**
     * Set an HTTP request object.
     *
     * @param  RequestInterface $request A PSR-7 compatible Request instance.
     * @return self
     */
    protected function setHttpRequest(RequestInterface $request)
    {
        $this->httpRequest = $request;

        return $this;
    }

    /**
     * Retrieve the HTTP response.
     *
     * @throws RuntimeException If the HTTP response was not previously set.
     * @return ResponseInterface
     */
    public function httpResponse()
    {
        if (!isset($this->httpResponse)) {
            throw new RuntimeException(
                sprintf('HTTP Response is not defined for "%s"', get_class($this))
            );
        }

        return $this->httpResponse;
    }

    /**
     * Set an HTTP response object.
     *
     * @param  ResponseInterface $response A PSR-7 compatible Response instance.
     * @return self
     */
    protected function setHttpResponse(ResponseInterface $response)
    {
        $this->httpResponse = $response;

        return $this;
    }

    /**
     * Update the action's {@see ResponseInterface} with the specified status code and,
     * optionally, reason phrase.
     *
     * @param integer $code         The 3-digit integer result code to set.
     * @param string  $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     * @return self
     */
    protected function updateResponseWithStatus($code, $reasonPhrase = '')
    {
        $this->httpResponse = $this->httpResponse->withStatus($code, $reasonPhrase);

        return $this;
    }

    /**
     * Is this response successful?
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        $response = $this->httpResponse();

        return ($response->isSuccessful() || $response->isInformational());
    }
}
