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

class CurrencyUpdateRateEvent extends ActionEvent
{
    protected $undefinedRates = [];

    /**
     * @param int $currencyId
     */
    public function addUndefinedRate($currencyId): void
    {
        $this->undefinedRates[] = $currencyId;
    }

    public function hasUndefinedRates(): bool
    {
        return !empty($this->undefinedRates);
    }

    /**
     * @return array of currency objects
     */
    public function getUndefinedRates()
    {
        return $this->undefinedRates;
    }
}
