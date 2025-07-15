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

class ProductCreateEvent extends ProductEvent
{
    protected ?string $ref = null;
    protected ?string $title = null;
    protected ?string $locale = null;
    protected ?int $default_category = null;
    protected ?bool $visible = null;
    protected ?bool $virtual = null;
    protected ?float $basePrice = null;
    protected ?float $baseWeight = null;
    protected ?int $taxRuleId = null;
    protected ?int $currencyId = null;
    protected ?int $baseQuantity = null;
    protected ?int $templateId = null;

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): static
    {
        $this->ref = $ref;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDefaultCategory(): ?int
    {
        return $this->default_category;
    }

    public function setDefaultCategory(?int $default_category): static
    {
        $this->default_category = $default_category;

        return $this;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(bool|int $visible): static
    {
        $this->visible = (bool) $visible;

        return $this;
    }

    public function setVirtual(?bool $virtual): static
    {
        $this->virtual = $virtual;

        return $this;
    }

    public function getVirtual(): ?bool
    {
        return $this->virtual;
    }

    public function getBasePrice(): ?float
    {
        return $this->basePrice;
    }

    public function setBasePrice(?float $basePrice): static
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getBaseWeight(): ?float
    {
        return $this->baseWeight;
    }

    public function setBaseWeight(?float $baseWeight): static
    {
        $this->baseWeight = $baseWeight;

        return $this;
    }

    public function getTaxRuleId(): ?int
    {
        return $this->taxRuleId;
    }

    public function setTaxRuleId(?int $taxRuleId): static
    {
        $this->taxRuleId = $taxRuleId;

        return $this;
    }

    public function getCurrencyId(): ?int
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?int $currencyId): static
    {
        $this->currencyId = $currencyId;

        return $this;
    }

    /**
     * This method is an alias of setBasePrice and used by the event when binding a form.
     *
     * @param float|null $price price for this new product
     *
     * @return $this
     *
     * @see setBasePrice
     */
    public function setPrice(?float $price): static
    {
        return $this->setBasePrice($price);
    }

    /**
     * This method is an alias of setBaseWeight and used by the event when binding a form.
     *
     * @return $this
     *
     * @see setBaseWeight
     */
    public function setWeight(?float $weight): static
    {
        return $this->setBaseWeight($weight);
    }

    /**
     * This method is an alias of setCurrencyId and used by the event when binding a form.
     *
     * @return $this
     *
     * @see setCurrencyId
     */
    public function setCurrency(?int $currencyId): static
    {
        return $this->setCurrencyId($currencyId);
    }

    /**
     * This method is an alias of setTaxRuleId and used by the event when binding a form.
     *
     * @return $this
     *
     * @see setTaxRuleId
     */
    public function setTaxRule(?int $taxRuleId): static
    {
        return $this->setTaxRuleId($taxRuleId);
    }

    public function getBaseQuantity(): ?int
    {
        return $this->baseQuantity;
    }

    /**
     * @return $this
     */
    public function setBaseQuantity(?int $baseQuantity): static
    {
        $this->baseQuantity = $baseQuantity;

        return $this;
    }

    /**
     * This method is an alias of setBaseQuantity and used by the event when binding a form.
     *
     * @param int|null $quantity quantity for this new product
     *
     * @return $this
     *
     * @see setBaseQuantity
     */
    public function setQuantity(?int $quantity): static
    {
        return $this->setBaseQuantity($quantity);
    }

    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    /**
     * @return $this
     */
    public function setTemplateId(?int $templateId): static
    {
        $this->templateId = $templateId;

        return $this;
    }
}
