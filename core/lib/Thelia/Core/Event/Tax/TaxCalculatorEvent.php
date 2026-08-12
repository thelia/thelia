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

namespace Thelia\Core\Event\Tax;

use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;

/**
 * Carries the tax calculator to use for one computation.
 *
 * Dispatched as TheliaEvents::TAX_GET_CALCULATOR by call sites that have no
 * access to the container, typically Propel models. Thelia answers it last, so
 * any listener that sets a calculator wins over the default one.
 */
class TaxCalculatorEvent extends ActionEvent
{
    protected ?TaxCalculatorInterface $taxCalculator = null;

    public function hasTaxCalculator(): bool
    {
        return $this->taxCalculator instanceof TaxCalculatorInterface;
    }

    public function getTaxCalculator(): ?TaxCalculatorInterface
    {
        return $this->taxCalculator;
    }

    public function setTaxCalculator(TaxCalculatorInterface $taxCalculator): static
    {
        $this->taxCalculator = $taxCalculator;

        return $this;
    }
}
