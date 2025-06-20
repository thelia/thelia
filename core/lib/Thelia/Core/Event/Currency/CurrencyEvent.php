<?php

declare(strict_types=1);

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
    protected $currencyId;

    public function __construct(protected ?Currency $currency = null)
    {
    }

    public function hasCurrency(): bool
    {
        return $this->currency instanceof Currency;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    /**
     * @return $this
     */
    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCurrencyId()
    {
        return $this->currencyId;
    }

    /**
     * @param int $currencyId
     *
     * @return $this
     */
    public function setCurrencyId($currencyId): static
    {
        $this->currencyId = $currencyId;

        return $this;
    }
}
