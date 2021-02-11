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

namespace Thelia\Core\Event\Currency;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Currency;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\CurrencyEvent
 */
class CurrencyEvent extends ActionEvent
{
    protected $currency;

    protected $currencyId;

    public function __construct(Currency $currency = null)
    {
        $this->currency = $currency;
    }

    /**
     * @return bool
     */
    public function hasCurrency()
    {
        return ! \is_null($this->currency);
    }

    /**
     * @return null|Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return $this
     */
    public function setCurrency(Currency $currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     */
    public function getCurrencyId()
    {
        return $this->currencyId;
    }

    /**
     * @param int $currencyId
     * @return $this
     */
    public function setCurrencyId($currencyId)
    {
        $this->currencyId = $currencyId;

        return $this;
    }
}
