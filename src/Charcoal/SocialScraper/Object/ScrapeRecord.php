<?php

namespace Charcoal\SocialScraper\Object;

use DateTime;
use DateTimeInterface;
use Traversable;
use InvalidArgumentException;

// From 'charcoal-core'
use Charcoal\Model\AbstractModel as CharcoalModel;

// From 'charcoal-support'
use Charcoal\Support\Property\ParsableValueTrait;

/**
 * Scrape records should be saved every time a client initiates a scrape request.
 */
class ScrapeRecord extends CharcoalModel
{
    use ParsableValueTrait;

    /** @const string Standard response for successful API requests. */
    const STATUS_OK = 'OK';

    /** @const string Standard response for failed API requests. */
    const STATUS_FAIL = 'FAIL';

    /** @const string Standard response for unprocessed API requests (fresh data is present). */
    const STATUS_HIT = 'HIT';

    /** @const string Standard response for unprocessed API requests (either stale or no data). */
    const STATUS_MISS = 'MISS';

    /**
     * The scrape record identifier.
     *
     * @var string|null
     */
    protected $ident;

    /**
     * The social network identifier.
     *
     * @var string|null
     */
    protected $network;

    /**
     * The scrape record repository.
     *
     * @var string|null
     */
    protected $repository;

    /**
     * The scrape record method.
     *
     * @var string|null
     */
    protected $method;

    /**
     * The scrape record filters.
     *
     * @var array|string|null
     */
    protected $filters;

    /**
     * The API response summary.
     *
     * @var string[]|string||null
     */
    protected $status;

    /**
     * Timestamp of the scrape request.
     *
     * @var DateTimeInterface|null
     */
    protected $logDate;

    /**
     * The scrape origin; an identifier representing where the scrape was executed from.
     *
     * @var string|null
     */
    private $origin;

    /**
     * Attempt to load the latest record according to this model's data.
     *
     * @param  DateTimeInterface|null $expiresAt Filter the latest record by expiration timestamp.
     * @return ScrapeRecord Chainable
     */
    public function loadLatestRecord(DateTimeInterface $expiresAt = null)
    {
        $source = $this->source();

        if (!$source->tableExists()) {
            return $this;
        }

        $sql = strtr('SELECT * FROM `%table` WHERE `status` = "OK"', [
            '%table' => $source->table()
        ]);

        $props  = [ 'network', 'repository', 'method', 'filters' ];
        $fields = [];
        foreach ($props as $p) {
            $v = $this[$p];

            if (empty($v) && !is_numeric($v)) {
                continue;
            }

            $fields[$p] = $v;
            $sql .= strtr(' AND `%prop` = :%prop', [
                '%prop' => $p
            ]);
        }

        if (empty($fields)) {
            return $this;
        }

        if ($expiresAt) {
            $fields['log_date'] = $expiresAt->format('Y-m-d H:i:s');
            $sql .= ' AND `log_date` > :log_date';
        }

        $sql .= ' ORDER BY `log_date` DESC LIMIT 1';

        $this->loadFromQuery($sql, $fields);

        return $this;
    }

    /**
     * Generate an ident from the object's repository, method, and filter properties.
     *
     * @deprecated
     * @return string
     */
    public function generateIdent()
    {
        $ident = [
            $this->network(),
            $this->repository(),
            $this->method(),
            $this->filters()
        ];
        return strtolower(implode('/', $ident));
    }

    /**
     * Set the scrape record's identifier.
     *
     * @deprecated
     * @param  string $ident The scrape record.
     * @throws InvalidArgumentException If the identifier is not a string.
     * @return ScrapeRecord Chainable
     */
    public function setIdent($ident)
    {
        if (!is_string($ident)) {
            throw new InvalidArgumentException(
                'Scrape ident must be a string.'
            );
        }

        $this->ident = $ident;

        return $this;
    }

    /**
     * Retrieve the scrape record's identifier.
     *
     * @deprecated
     * @return string
     */
    public function ident()
    {
        return $this->ident;
    }

    /**
     * Set the scrape network.
     *
     * @param  string $network The social network identifier.
     * @throws InvalidArgumentException If the network is not a string.
     * @return ScrapeRecord Chainable
     */
    public function setNetwork($network)
    {
        if (!is_string($network)) {
            throw new InvalidArgumentException(
                'Network must be a string.'
            );
        }

        $this->network = $network;

        return $this;
    }

    /**
     * Retrieve the network name.
     *
     * @return string
     */
    public function network()
    {
        return $this->network;
    }

    /**
     * Set the scrape repository.
     *
     * @param  string $repository The repository name.
     * @throws InvalidArgumentException If the repository is not a string.
     * @return ScrapeRecord Chainable
     */
    public function setRepository($repository)
    {
        if (!is_string($repository)) {
            throw new InvalidArgumentException(
                'Repository must be a string.'
            );
        }

        $this->repository = $repository;

        return $this;
    }

    /**
     * Retrieve the repository name.
     *
     * @return string
     */
    public function repository()
    {
        return $this->repository;
    }

    /**
     * Set the scrape method.
     *
     * @param  string $method The method name.
     * @throws InvalidArgumentException If the method is not a string.
     * @return ScrapeRecord Chainable
     */
    public function setMethod($method)
    {
        if (!is_string($method)) {
            throw new InvalidArgumentException(
                'Method must be a string.'
            );
        }

        $this->method = $method;

        return $this;
    }

    /**
     * Retrieve the method name.
     *
     * @return string
     */
    public function method()
    {
        return $this->method;
    }

    /**
     * Set the scrape filters as key:value pairs.
     *
     * @param  mixed $filters One or more filters.
     * @return ScrapeRecord Chainable
     */
    public function setFilters($filters)
    {
        if (is_array($filters)) {
            $this->filters = json_encode($filters);
        }

        return $this;
    }

    /**
     * Retrieve the scrape record filters.
     *
     * @return string
     */
    public function filters()
    {
        return $this->filters;
    }

    /**
     * Set the summary/notes of the API's response.
     *
     * @param  string|string[] $status A status code, message, or extra information about the response.
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $this->parseAsMultiple($status);

        return $this;
    }

    /**
     * Retrieve the summary/notes of the API's response.
     *
     * @return string[]|null
     */
    public function status()
    {
        if (empty($this->status)) {
            $this->status = self::STATUS_OK;
        }

        return $this->status;
    }

    /**
     * Set when the scrape was requested.
     *
     * @param  string|DateTime $time A date/time value. Valid formats are explained in
     *     {@link http://php.net/manual/en/datetime.formats.php Date and Time Formats}.
     * @return self
     */
    public function setLogDate($time)
    {
        $this->logDate = $this->parseAsDateTime($time);

        return $this;
    }

    /**
     * Retrieve the scrape's request timestamp.
     *
     * @return DateTimeInterface|null
     */
    public function logDate()
    {
        return $this->logDate;
    }
    /**
     * Set the origin of the scrape request.
     *
     * @param  string $origin The source URL or identifier of the submission.
     * @throws InvalidArgumentException If the argument is not a string.
     * @return SearchLog Chainable
     */
    public function setOrigin($origin)
    {
        if ($origin !== null) {
            if (!is_string($origin)) {
                throw new InvalidArgumentException(
                    'Origin must be a string.'
                );
            }
        }

        $this->origin = $origin;

        return $this;
    }

    /**
     * Resolve the origin of the scrape request.
     *
     * @return string
     */
    public function resolveOrigin()
    {
        $uri = 'http';

        if (getenv('HTTPS') === 'on') {
            $uri .= 's';
        }

        $uri .= '://';
        $uri .= getenv('HTTP_HOST').getenv('REQUEST_URI');

        return $uri;
    }

    /**
     * Retrieve the origin of the scrape request.
     *
     * @return string
     */
    public function origin()
    {
        return $this->origin;
    }

    /**
     * Event called before _creating_ the object.
     *
     * @see    Charcoal\Source\StorableTrait::preSave() For the "create" Event.
     * @return boolean
     */
    public function preSave()
    {
        $this->setLogDate('now');
        $this->setIdent($this->generateIdent());
        if (!isset($this->origin)) {
            $this->setOrigin($this->resolveOrigin());
        }

        return parent::preSave();
    }
}
