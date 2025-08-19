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

namespace Thelia\Model;

/**
 * Class OrderPostage.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class OrderPostage
{
    protected ?float $amount = null;
    protected ?float $amountTax = null;
    protected ?float $untaxedAmount = null;
    protected ?string $taxRuleTitle = null;

    public function __construct(?string $amount = null, ?string $amountTax = null, ?string $taxRuleTitle = null)
    {
    }

    /**
     * Convert a amount or OrderPostage object to an OrderPostage object.
     */
    public static function loadFromPostage(self|float $postage): self
    {
        return $postage instanceof self ? $postage : new self($postage);
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount = null): void
    {
        $this->amount = $amount;
    }

    public function getAmountTax(): ?float
    {
        return $this->amountTax;
    }

    public function setAmountTax(?float $amountTax = null): void
    {
        // We have to round the postage tax to prevent small delta amounts in tax calculations.
        $this->amountTax = round($amountTax, 2);
    }

    public function getTaxRuleTitle(): ?string
    {
        return $this->taxRuleTitle;
    }

    public function setTaxRuleTitle(?string $taxRuleTitle = null): void
    {
        $this->taxRuleTitle = $taxRuleTitle;
    }

    public function getUntaxedAmount(): ?float
    {
        return $this->untaxedAmount ?: $this->amount - $this->amountTax;
    }

    public function setUntaxedAmount(float $untaxedAmount): void
    {
        $this->untaxedAmount = $untaxedAmount;
    }
}
