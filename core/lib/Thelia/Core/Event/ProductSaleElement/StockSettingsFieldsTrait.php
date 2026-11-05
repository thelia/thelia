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

namespace Thelia\Core\Event\ProductSaleElement;

/**
 * The stock settings of a sale element that an event can carry.
 *
 * A field that was never set is left alone by the action, so a caller that does
 * not know these settings never wipes them. Setting a field to null clears it
 * (the out of stock policy goes back to 0, refuse).
 */
trait StockSettingsFieldsTrait
{
    protected ?int $outOfStockPolicy = null;
    protected ?float $preorderLimit = null;
    protected ?\DateTimeInterface $restockDate = null;
    protected ?float $lowStockThreshold = null;

    /** @var array<string, true> */
    private array $stockSettingsProvided = [];

    public function getOutOfStockPolicy(): ?int
    {
        return $this->outOfStockPolicy;
    }

    public function setOutOfStockPolicy(?int $outOfStockPolicy): static
    {
        $this->outOfStockPolicy = $outOfStockPolicy;
        $this->stockSettingsProvided['outOfStockPolicy'] = true;

        return $this;
    }

    public function hasOutOfStockPolicy(): bool
    {
        return isset($this->stockSettingsProvided['outOfStockPolicy']);
    }

    public function getPreorderLimit(): ?float
    {
        return $this->preorderLimit;
    }

    public function setPreorderLimit(?float $preorderLimit): static
    {
        $this->preorderLimit = $preorderLimit;
        $this->stockSettingsProvided['preorderLimit'] = true;

        return $this;
    }

    public function hasPreorderLimit(): bool
    {
        return isset($this->stockSettingsProvided['preorderLimit']);
    }

    public function getRestockDate(): ?\DateTimeInterface
    {
        return $this->restockDate;
    }

    public function setRestockDate(?\DateTimeInterface $restockDate): static
    {
        $this->restockDate = $restockDate;
        $this->stockSettingsProvided['restockDate'] = true;

        return $this;
    }

    public function hasRestockDate(): bool
    {
        return isset($this->stockSettingsProvided['restockDate']);
    }

    public function getLowStockThreshold(): ?float
    {
        return $this->lowStockThreshold;
    }

    public function setLowStockThreshold(?float $lowStockThreshold): static
    {
        $this->lowStockThreshold = $lowStockThreshold;
        $this->stockSettingsProvided['lowStockThreshold'] = true;

        return $this;
    }

    public function hasLowStockThreshold(): bool
    {
        return isset($this->stockSettingsProvided['lowStockThreshold']);
    }
}
