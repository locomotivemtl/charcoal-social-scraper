<?php

namespace Charcoal\Twitter\Object;

use \DateTime;
use \DateTimeInterface;
use \InvalidArgumentException;

// From `pimple`
use \Pimple\Container;

// From `charcoal-factory`
use \Charcoal\Factory\FactoryInterface;

// From `charcoal-core`
use \Charcoal\Model\AbstractModel;

// From `charcoal-support`
use \Charcoal\Support\Container\DependentInterface;
use \Charcoal\Support\Model\ManufacturableModelTrait;
use \Charcoal\Support\Model\ManufacturableModelCollectionTrait;

/**
 * Twitter Tag Object
 */
class Tag extends AbstractModel
{
    /**
     * Objects are active by default.
     *
     * @var boolean $active
     */
    protected $active = true;

    // Setters and getters
    // =================================================================================================================
    /**
     * Set the object's ID. The actual property set depends on `key()`.
     * Basically forcing lowercase.
     *
     * @param mixed $id The object id (identifier / primary key value).
     * @throws InvalidArgumentException If the argument is not scalar.
     * @return StorableInterface Chainable
     */
    public function setId($id)
    {
        if (!is_scalar($id)) {
            throw new InvalidArgumentException(
                sprintf(
                    'ID must be a scalar (integer, float, string, or boolean); received %s',
                    (is_object($id) ? get_class($id) : gettype($id))
                )
            );
        }

        $id = strtolower($id);

        $key = $this->key();
        if ($key == 'id') {
            $this->id = $id;
        } else {
            $this[$key] = $id;
        }

        return $this;
    }

    /**
     * @param boolean $active The active flag.
     * @return Content Chainable
     */
    public function setActive($active)
    {
        $this->active = !!$active;

        return $this;
    }

    /**
     * @return boolean
     */
    public function active()
    {
        return $this->active;
    }
}
