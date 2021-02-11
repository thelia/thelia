<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaMigrateCountry\Events;

use Thelia\Core\Event\ActionEvent;

/**
 * Class MigrateCountryEvent
 * @package TheliaMigrateCountry\Events
 * @author Julien Chanséaume <julien@thelia.net>
 */
class MigrateCountryEvent extends ActionEvent
{
    /** @var int Old country Id */
    protected $country;

    /** @var int New country Id */
    protected $newCountry;

    /** @var int New state Id */
    protected $newState;

    /** @var array counter */
    protected $counter = [];

    /**
     * MigrateCountryEvent constructor.
     * @param $country
     * @param int $newCountry
     * @param int $newState
     */
    public function __construct($country, $newCountry, $newState)
    {
        $this->country = $country;
        $this->newCountry = $newCountry;
        $this->newState = $newState;
    }

    /**
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     */
    public function setCountry($country)
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @return int
     */
    public function getNewCountry()
    {
        return $this->newCountry;
    }

    /**
     * @param int $newCountry
     */
    public function setNewCountry($newCountry)
    {
        $this->newCountry = $newCountry;
        return $this;
    }

    /**
     * @return int
     */
    public function getNewState()
    {
        return $this->newState;
    }

    /**
     * @param int $newState
     */
    public function setNewState($newState)
    {
        $this->newState = $newState;
        return $this;
    }

    /**
     * @return array
     */
    public function getCounter()
    {
        return $this->counter;
    }

    /**
     * @param array $counter
     */
    public function setCounter($counter)
    {
        $this->counter = $counter;
        return $this;
    }
}
