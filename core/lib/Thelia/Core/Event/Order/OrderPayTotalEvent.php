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

namespace Thelia\Core\Event\Order;

/**
 * Class OrderPayTotalEvent.
 */
class OrderPayTotalEvent extends OrderEvent
{
    /** @var float|int */
    protected $tax;

    /** @var bool */
    protected $includePostage;

    /** @var bool */
    protected $includeDiscount;

    /** @var float|int */
    protected $total;

    public function isIncludePostage(): bool
    {
        return $this->includePostage;
    }

    public function setIncludePostage(bool $includePostage): self
    {
        $this->includePostage = $includePostage;

        return $this;
    }

    public function isIncludeDiscount(): bool
    {
        return $this->includeDiscount;
    }

    public function setIncludeDiscount(bool $includeDiscount): self
    {
        $this->includeDiscount = $includeDiscount;

        return $this;
    }

    public function getTax(): float|int
    {
        return $this->tax;
    }

    public function setTax(float|int $tax): self
    {
        $this->tax = $tax;

        return $this;
    }

    public function getTotal(): float|int
    {
        return $this->total;
    }

    public function setTotal(float|int $total): self
    {
        $this->total = $total;

        return $this;
    }
}
