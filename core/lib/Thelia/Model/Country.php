<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Country\CountryEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Country as BaseCountry;

class Country extends BaseCountry
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    public function toggleDefault()
    {
        CountryQuery::create()
            ->filterByByDefault(1)
            ->update(array('ByDefault' => 0));

        $this
            ->setByDefault(1)
            ->save();
    }

    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATECOUNTRY, new CountryEvent($this));
        return true;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATECOUNTRY, new CountryEvent($this));
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATECOUNTRY, new CountryEvent($this));

        return true;
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATECOUNTRY, new CountryEvent($this));
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETECOUNTRY, new CountryEvent($this));

        return true;
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETECOUNTRY, new CountryEvent($this));
    }

}
