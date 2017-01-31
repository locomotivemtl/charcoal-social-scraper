<?php

namespace Charcoal\SocialScraper\Script;

use Exception as PhpException;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-social-scraper'
use Charcoal\SocialScraper\Script\AbstractScraperScript;
use Charcoal\SocialScraper\Exception\Exception as ScraperException;
use Charcoal\SocialScraper\Exception\HitException;
use Charcoal\SocialScraper\Exception\NotFoundException;
use Charcoal\SocialScraper\Exception\RateLimitException;
use Charcoal\SocialScraper\Traits\ImportableTrait;

/**
 * CLI: Import posts from Social Networks
 */
class ImportScript extends AbstractScraperScript
{
    use ImportableTrait;

    /**
     * @return void
     */
    protected function init()
    {
        parent::init();

        $this->setDescription(
            'The <underline>social-scraper/import/recent</underline> script scrapes the latest posts from social networks.'
        );
    }

    /**
     * Run the script.
     *
     * @param  RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param  ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        unset($request);

        try {
            $this->import();
        } catch (PhpException $e) {
            $this->climate()->error($e->getMessage());
        }

        return $response;
    }

    /**
     * Import Posts
     *
     * @todo   Add support for 'user_id', 'screen_name', 'count', 'min_id', 'max_id'.
     * @return self
     */
    public function import()
    {
        $cli  = $this->climate();
        $args = $cli->arguments;
        $ask  = $args->defined('interactive');
        $dry  = $args->defined('dry_run');
        $tab  = '   ';

        $cli->br();
        $cli->bold()->underline()->out('Import Latest Posts from Social Networks');
        $cli->br();

        $this->parseArguments();

        if (!$this->quiet()) {
            $sources = $this->config('scrapers');
            $labels  = [];
            foreach ($sources as $ident) {
                $scraper  = $this->scraper($ident);
                $labels[] = $scraper->label();
            }

            $input = $cli->confirm(sprintf(
                $tab.'Import "%s"?',
                implode('", "', $labels)
            ));
            if ($input->confirmed()) {
                $cli->info($tab.'Starting Import');
            } else {
                $cli->info($tab.'Canceled Import');
                return $this;
            }
        }

        try {
            $request = $this->config('request');
            $sources = $this->config('scrapers');
            foreach ($sources as $ident) {
                $scraper = $this->scraper($ident);
                $results = $scraper->scrape($request);
                if (!$this->quiet()) {
                    if ($results) {
                        $count = count($results);
                        $cli->comment(sprintf(
                            $tab.'-  Scraped %d %s from "%s".',
                            $count,
                            ($count === 1 ? 'item' : 'items'),
                            $scraper->label()
                        ));
                    } else {
                        $cli->comment($tab.sprintf('-  Nothing new from "%s".', $scraper->label()));
                    }
                }
            }
        } catch (HitException $e) {
            if (!$this->quiet()) {
                $cli->comment($tab.$e->getMessage());
            }
        } catch (ScraperException $e) {
            $cli->error($tab.$e->getMessage());
        } catch (PhpException $e) {
            $cli->error($tab.$e->getMessage());
        }

        if (!$this->quiet()) {
            $cli->br();
            $cli->info($tab.'Done!');
        }

        return $this;
    }

    /**
     * Retrieve the script's supported arguments.
     *
     * @see    ImportableTrait::$defaultConfig for supported arguments.
     * @return array
     */
    public function defaultArguments()
    {
        static $arguments;

        if ($arguments === null) {
            $validateScrapers = function ($response) {
                if (strlen($response) === 0) {
                    return false;
                }

                try {
                    $arr = $this->parseAsArray($response);
                    return !!array_intersect($this->availableScrapers(), array_unique($arr));
                } catch (PhpException $e) {
                    unset($e);

                    return false;
                }

                return true;
            };

            $validateTags = function ($response) {
                if (strlen($response) === 0) {
                    return true;
                }

                try {
                    $this->parseAsArray($response);
                } catch (PhpException $e) {
                    unset($e);
                    return false;
                }

                return true;
            };

            $arguments = [
                'scrapers' => [
                    'longPrefix'  => 'scraper',
                    'required'    => true,
                    'description' => 'The web scraper(s) to use.',
                    'prompt'      => 'Which social network(s) to scrape?',
                    'acceptValue' => $validateScrapers->bindTo($this)
                ],
                'request' => [
                    'longPrefix'  => 'request',
                    'description' => 'The preset scrape request to run.',
                    'prompt'      => 'Which preset request to run?'
                ],
                'tags' => [
                    'longPrefix'  => 'tags',
                    'description' => 'The hashtag(s) to scrape for.',
                    'prompt'      => 'Filter by tag:',
                    'acceptValue' => $validateTags->bindTo($this)
                ]
            ];

            $arguments = array_merge(parent::defaultArguments(), $arguments);
        }

        return $arguments;
    }
}
