<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Event\Currency\CurrencyEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Currency as BaseCurrency;

class Currency extends BaseCurrency
{
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
        parent::preInsert($con);

        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        return true;
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
