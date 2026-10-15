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

namespace Thelia\Core\Event\Product;

use Thelia\Domain\Shipping\ProductRule\ShippingSurchargeMode;

class ProductUpdateEvent extends ProductCreateEvent
{
    protected $chapo;
    protected $description;
    protected $postscriptum;
    protected $brand_id;
    protected $virtual_document_id;
    protected ?bool $guestCheckoutForbidden = null;
    protected ?float $shippingSurcharge = null;
    protected ?ShippingSurchargeMode $shippingSurchargeMode = null;
    protected ?bool $freeShipping = null;

    /**
     * @param int $product_id
     */
    public function __construct(protected $product_id)
    {
    }

    public function getProductId()
    {
        return $this->product_id;
    }

    public function setProductId($product_id): static
    {
        $this->product_id = $product_id;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setChapo($chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setPostscriptum($postscriptum): static
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    /**
     * @return $this
     */
    public function setBrandId(?int $brand_id): static
    {
        $this->brand_id = $brand_id;

        return $this;
    }

    public function getBrandId(): ?int
    {
        return $this->brand_id;
    }

    /**
     * @return $this
     */
    public function setVirtualDocumentId($virtual_document_id): static
    {
        $this->virtual_document_id = $virtual_document_id;

        return $this;
    }

    public function getVirtualDocumentId()
    {
        return $this->virtual_document_id;
    }

    /**
     * Null means the caller does not carry this field: leave the column untouched.
     */
    public function getGuestCheckoutForbidden(): ?bool
    {
        return $this->guestCheckoutForbidden;
    }

    /**
     * @return $this
     */
    public function setGuestCheckoutForbidden(?bool $guestCheckoutForbidden): static
    {
        $this->guestCheckoutForbidden = $guestCheckoutForbidden;

        return $this;
    }

    /**
     * The untaxed surcharge, in the default currency. Null leaves the column untouched.
     */
    public function getShippingSurcharge(): ?float
    {
        return $this->shippingSurcharge;
    }

    public function setShippingSurcharge(?float $shippingSurcharge): static
    {
        $this->shippingSurcharge = $shippingSurcharge;

        return $this;
    }

    /**
     * Null leaves the column untouched.
     */
    public function getShippingSurchargeMode(): ?ShippingSurchargeMode
    {
        return $this->shippingSurchargeMode;
    }

    public function setShippingSurchargeMode(?ShippingSurchargeMode $shippingSurchargeMode): static
    {
        $this->shippingSurchargeMode = $shippingSurchargeMode;

        return $this;
    }

    /**
     * Null leaves the column untouched.
     */
    public function getFreeShipping(): ?bool
    {
        return $this->freeShipping;
    }

    public function setFreeShipping(?bool $freeShipping): static
    {
        $this->freeShipping = $freeShipping;

        return $this;
    }
}
