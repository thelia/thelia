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

namespace Thelia\Core\Event\Sale;

use Propel\Runtime\Util\PropelDateTime;

/**
 * Class SaleUpdateEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleUpdateEvent extends SaleCreateEvent
{
    protected $chapo;
    protected $description;
    protected $postscriptum;
    protected $active;
    protected $startDate;
    protected $endDate;
    protected $priceOffsetType;
    protected $displayInitialPrice;
    protected $priceOffsets;
    protected $products;
    protected $productAttributes;

    /**
     * @param int $saleId
     */
    public function __construct(protected $saleId)
    {
    }

    /**
     * @return SaleUpdateEvent $this
     */
    public function setSaleId(int $saleId): static
    {
        $this->saleId = $saleId;

        return $this;
    }

    public function getSaleId(): int
    {
        return (int) $this->saleId;
    }

    /**
     * @return SaleUpdateEvent $this
     */
    public function setChapo(?string $chapo): static
    {
        $this->chapo = $chapo ?? '';

        return $this;
    }

    public function getChapo(): string
    {
        return $this->chapo;
    }

    /**
     * @return SaleUpdateEvent $this
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description ?? '';

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return SaleUpdateEvent $this
     */
    public function setPostscriptum(?string $postscriptum): static
    {
        $this->postscriptum = $postscriptum ?? '';

        return $this;
    }

    public function getPostscriptum(): string
    {
        return $this->postscriptum;
    }

    /**
     * @return SaleUpdateEvent $this
     */
    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    /**
     * @param mixed $endDate string, integer (timestamp), or \DateTime value
     *
     * @return SaleUpdateEvent $this
     */
    public function setEndDate(mixed $endDate): static
    {
        $this->endDate = PropelDateTime::newInstance($endDate, null, '\DateTime');

        return $this;
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    /**
     * @return SaleUpdateEvent $this
     */
    public function setPriceOffsetType(int $priceOffsetType): static
    {
        $this->priceOffsetType = $priceOffsetType;

        return $this;
    }

    public function getPriceOffsetType(): int
    {
        return $this->priceOffsetType;
    }

    /**
     * @param mixed $startDate string, integer (timestamp), or \DateTime value
     *
     * @return SaleUpdateEvent $this
     */
    public function setStartDate(mixed $startDate): static
    {
        $this->startDate = PropelDateTime::newInstance($startDate, null, '\DateTime');

        return $this;
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    /**
     * @return $this
     */
    public function setDisplayInitialPrice(bool $displayInitialPrice): static
    {
        $this->displayInitialPrice = $displayInitialPrice;

        return $this;
    }

    public function getDisplayInitialPrice(): bool
    {
        return $this->displayInitialPrice;
    }

    /**
     * @param array $priceOffsets an array of (currency_id => price offset) couples
     *
     * @return $this
     */
    public function setPriceOffsets(array $priceOffsets): static
    {
        $this->priceOffsets = $priceOffsets;

        return $this;
    }

    public function getPriceOffsets(): array
    {
        return $this->priceOffsets;
    }

    /**
     * @param array $products an array of product ID
     *
     * @return $this
     */
    public function setProducts(array $products): static
    {
        $this->products = $products;

        return $this;
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * @param array $productAttributes an array of (product_id => array of attribute IDs)
     *
     * @return $this
     */
    public function setProductAttributes(array $productAttributes): static
    {
        $this->productAttributes = $productAttributes;

        return $this;
    }

    /**
     * @return array $productAttributes an array of (product_id => array of attribute IDs)
     */
    public function getProductAttributes(): array
    {
        return $this->productAttributes;
    }
}
