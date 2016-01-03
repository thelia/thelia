<?php

namespace Thelia\Model;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Base\Currency as BaseCurrency;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Currency\CurrencyEvent;

class Currency extends BaseCurrency
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    protected static $defaultCurrency = null;

    public static function getDefaultCurrency()
    {
        if (null === self::$defaultCurrency) {
            self::$defaultCurrency = CurrencyQuery::create()->findOneByByDefault(1);

            if (null === self::$defaultCurrency) {
                throw new \RuntimeException("No default currency is defined. Please define one.");
            }
        }

        return self::$defaultCurrency;
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATECURRENCY, new CurrencyEvent($this));

        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATECURRENCY, new CurrencyEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATECURRENCY, new CurrencyEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATECURRENCY, new CurrencyEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETECURRENCY, new CurrencyEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETECURRENCY, new CurrencyEvent($this));
    }

    /**
     * Get the [rate] column value.
     *
     * @return double
     * @throws PropelException
     */
    public function getRate()
    {
        if (false === filter_var($this->rate, FILTER_VALIDATE_FLOAT)) {
            throw new PropelException('Currency::rate is not float value');
        }

        return $this->rate;
    }
}
