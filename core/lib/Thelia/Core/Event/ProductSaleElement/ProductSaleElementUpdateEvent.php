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

use Thelia\Model\Product;

class ProductSaleElementUpdateEvent extends ProductSaleElementEvent
{
    protected int $product_sale_element_id;
    protected Product $product;
    protected string $reference;
    protected float $price;
    protected int $currency_id;
    protected float $weight;
    protected float $quantity;
    protected float $sale_price;
    protected int $onsale;
    protected int $isnew;
    protected bool $isdefault;
    protected ?string $ean_code;
    protected int $tax_rule_id;
    protected int $from_default_currency;

    /**
     * ProductSaleElementUpdateEvent constructor.
     */
    public function __construct(Product $product, int $product_sale_element_id)
    {
        parent::__construct();

        $this->setProduct($product);

        $this->setProductSaleElementId($product_sale_element_id);
    }

    public function getProductSaleElementId(): int
    {
        return $this->product_sale_element_id;
    }

    /**
     * @return $this
     */
    public function setProductSaleElementId(int $product_sale_element_id): self
    {
        $this->product_sale_element_id = $product_sale_element_id;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @return $this
     */
    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrencyId(): int
    {
        return $this->currency_id;
    }

    /**
     * @return $this
     */
    public function setCurrencyId(int $currency_id): self
    {
        $this->currency_id = $currency_id;

        return $this;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * @return $this
     */
    public function setWeight(float $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @return $this
     */
    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getSalePrice(): float
    {
        return $this->sale_price;
    }

    /**
     * @return $this
     */
    public function setSalePrice(float $sale_price): self
    {
        $this->sale_price = $sale_price;

        return $this;
    }

    public function getOnsale(): int
    {
        return $this->onsale;
    }

    /**
     * @return $this
     */
    public function setOnsale(int $onsale): self
    {
        $this->onsale = $onsale;

        return $this;
    }

    public function getIsnew(): int
    {
        return $this->isnew;
    }

    /**
     * @return $this
     */
    public function setIsnew(int $isnew): self
    {
        $this->isnew = $isnew;

        return $this;
    }

    public function getEanCode(): ?string
    {
        return $this->ean_code;
    }

    /**
     * @return $this
     */
    public function setEanCode(?string $ean_code): self
    {
        $this->ean_code = $ean_code;

        return $this;
    }

    public function getIsdefault(): bool
    {
        return $this->isdefault;
    }

    /**
     * @return $this
     */
    public function setIsdefault(bool $isdefault): self
    {
        $this->isdefault = $isdefault;

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return $this
     */
    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * @return $this
     */
    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getTaxRuleId(): int
    {
        return $this->tax_rule_id;
    }

    /**
     * @return $this
     */
    public function setTaxRuleId(int $tax_rule_id): static
    {
        $this->tax_rule_id = $tax_rule_id;

        return $this;
    }

    public function getFromDefaultCurrency(): int
    {
        return $this->from_default_currency;
    }

    /**
     * @return $this
     */
    public function setFromDefaultCurrency(int $from_default_currency): static
    {
        $this->from_default_currency = $from_default_currency;

        return $this;
    }
}
