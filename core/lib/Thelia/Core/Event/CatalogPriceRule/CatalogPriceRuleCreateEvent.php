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

namespace Thelia\Core\Event\CatalogPriceRule;

use Thelia\Model\CatalogPriceRule;

/**
 * Everything a rule is made of, as a screen or an API client posts it.
 *
 * The criteria are grouped by type (category, brand, template, feature_av,
 * attribute_av, product, or a type a module registered) and hold target ids: the
 * types combine with AND, the targets of one type with OR, and an absent type
 * means "all". The effect values are per currency and tax included, read for the
 * amount and fixed price effects; the percentage is read for the percentage one.
 */
class CatalogPriceRuleCreateEvent extends CatalogPriceRuleEvent
{
    protected string $locale = 'en_US';

    protected string $title = '';

    protected ?string $description = null;

    protected bool $active = false;

    protected int $priority = 100;

    protected bool $stopProcessing = false;

    protected ?\DateTimeInterface $startDate = null;

    protected ?\DateTimeInterface $endDate = null;

    protected int $effectType = CatalogPriceRule::EFFECT_TYPE_PERCENTAGE;

    protected ?float $percentageValue = null;

    /** @var array<int, float> currency id => amount off or fixed price, tax included */
    protected array $effectValuesByCurrency = [];

    protected int $audienceMode = CatalogPriceRule::AUDIENCE_MODE_PUBLIC;

    /** @var list<int> the customers a reserved rule prices for */
    protected array $customerIds = [];

    protected bool $displayInitialPrice = true;

    protected bool $includeSubcategories = true;

    /** @var array<string, list<int>> criterion type => target ids */
    protected array $criteria = [];

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setStopProcessing(bool $stopProcessing): static
    {
        $this->stopProcessing = $stopProcessing;

        return $this;
    }

    public function isStopProcessing(): bool
    {
        return $this->stopProcessing;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEffectType(int $effectType): static
    {
        $this->effectType = $effectType;

        return $this;
    }

    public function getEffectType(): int
    {
        return $this->effectType;
    }

    public function setPercentageValue(?float $percentageValue): static
    {
        $this->percentageValue = $percentageValue;

        return $this;
    }

    public function getPercentageValue(): ?float
    {
        return $this->percentageValue;
    }

    /**
     * @param array<int|string, float|int|string> $effectValuesByCurrency currency id => value, tax included
     */
    public function setEffectValuesByCurrency(array $effectValuesByCurrency): static
    {
        $this->effectValuesByCurrency = [];

        foreach ($effectValuesByCurrency as $currencyId => $value) {
            $this->effectValuesByCurrency[(int) $currencyId] = (float) $value;
        }

        return $this;
    }

    /**
     * @return array<int, float>
     */
    public function getEffectValuesByCurrency(): array
    {
        return $this->effectValuesByCurrency;
    }

    public function setAudienceMode(int $audienceMode): static
    {
        $this->audienceMode = $audienceMode;

        return $this;
    }

    public function getAudienceMode(): int
    {
        return $this->audienceMode;
    }

    /**
     * @param array<int|string, int|string> $customerIds
     */
    public function setCustomerIds(array $customerIds): static
    {
        $this->customerIds = array_values(array_unique(array_map('intval', $customerIds)));

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getCustomerIds(): array
    {
        return $this->customerIds;
    }

    public function setDisplayInitialPrice(bool $displayInitialPrice): static
    {
        $this->displayInitialPrice = $displayInitialPrice;

        return $this;
    }

    public function isDisplayInitialPrice(): bool
    {
        return $this->displayInitialPrice;
    }

    public function setIncludeSubcategories(bool $includeSubcategories): static
    {
        $this->includeSubcategories = $includeSubcategories;

        return $this;
    }

    public function isIncludeSubcategories(): bool
    {
        return $this->includeSubcategories;
    }

    /**
     * @param array<string, array<int|string, int|string>> $criteria type => target ids; an empty list drops the type
     */
    public function setCriteria(array $criteria): static
    {
        $this->criteria = [];

        foreach ($criteria as $type => $targetIds) {
            $ids = array_values(array_unique(array_map('intval', (array) $targetIds)));

            if ([] !== $ids) {
                $this->criteria[(string) $type] = $ids;
            }
        }

        return $this;
    }

    /**
     * @return array<string, list<int>>
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }
}
