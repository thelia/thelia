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

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\CatalogPriceRule as CatalogPriceRuleModel;
use Thelia\Model\Map\CatalogPriceRuleProductSaleElementsTableMap;
use Thelia\Model\Map\CatalogPriceRuleTableMap;

/**
 * A catalog price rule, read by the back office and by an integration.
 *
 * Read only, like the sale operation: a rule is written through its events, which
 * are what materialize its scope and rewrite the stored prices. Nothing writes
 * through here.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/catalog_price_rules',
        ),
        new Get(
            uriTemplate: '/admin/catalog_price_rules/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'audienceMode' => 'exact',
        'effectType' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'active',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'priority',
        'startDate',
        'endDate',
    ],
)]
class CatalogPriceRule extends AbstractTranslatableResource implements CollectionPreloadableInterface
{
    public const GROUP_ADMIN_READ = 'admin:catalog_price_rule:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:catalog_price_rule:read:single';

    /** @var array{products: int, sale_elements: int}|null the counts read for the whole page */
    private ?array $preloadedCoverage = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $active = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $priority = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $stopProcessing = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $startDate = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $endDate = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $effectType = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?float $percentageValue = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $audienceMode = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $displayInitialPrice = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $includeSubcategories = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $dirty = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $computedAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getStopProcessing(): ?bool
    {
        return $this->stopProcessing;
    }

    public function setStopProcessing(?bool $stopProcessing): self
    {
        $this->stopProcessing = $stopProcessing;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getEffectType(): ?int
    {
        return $this->effectType;
    }

    public function setEffectType(?int $effectType): self
    {
        $this->effectType = $effectType;

        return $this;
    }

    public function getPercentageValue(): ?float
    {
        return $this->percentageValue;
    }

    public function setPercentageValue(float|string|null $percentageValue): self
    {
        $this->percentageValue = null === $percentageValue ? null : (float) $percentageValue;

        return $this;
    }

    public function getAudienceMode(): ?int
    {
        return $this->audienceMode;
    }

    public function setAudienceMode(?int $audienceMode): self
    {
        $this->audienceMode = $audienceMode;

        return $this;
    }

    public function getDisplayInitialPrice(): ?bool
    {
        return $this->displayInitialPrice;
    }

    public function setDisplayInitialPrice(?bool $displayInitialPrice): self
    {
        $this->displayInitialPrice = $displayInitialPrice;

        return $this;
    }

    public function getIncludeSubcategories(): ?bool
    {
        return $this->includeSubcategories;
    }

    public function setIncludeSubcategories(?bool $includeSubcategories): self
    {
        $this->includeSubcategories = $includeSubcategories;

        return $this;
    }

    public function getDirty(): ?bool
    {
        return $this->dirty;
    }

    public function setDirty(?bool $dirty): self
    {
        $this->dirty = $dirty;

        return $this;
    }

    public function getComputedAt(): ?\DateTime
    {
        return $this->computedAt;
    }

    public function setComputedAt(?\DateTime $computedAt): self
    {
        $this->computedAt = $computedAt;

        return $this;
    }

    /**
     * The state the back office shows, derived from the flag and the dates.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public function getState(): ?string
    {
        return $this->propelRule()?->getStateAt(new \DateTimeImmutable());
    }

    /**
     * How many products the rule covers, as last materialized. Read for the whole
     * page by {@see preloadCollection()}.
     */
    #[Groups([self::GROUP_ADMIN_READ])]
    public function getAffectedProductCount(): int
    {
        return $this->coverage()['products'];
    }

    #[Groups([self::GROUP_ADMIN_READ])]
    public function getAffectedSaleElementCount(): int
    {
        return $this->coverage()['sale_elements'];
    }

    /**
     * @return array<string, list<int>> criterion type => target ids
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public function getCriteria(): array
    {
        return $this->propelRule()?->getCriteriaByType() ?? [];
    }

    /**
     * @return array<int, float> currency id => amount or fixed price, tax included
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public function getEffectValuesByCurrency(): array
    {
        return $this->propelRule()?->getEffectValuesByCurrency() ?? [];
    }

    /**
     * @return list<int>
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public function getCustomerIds(): array
    {
        $rule = $this->propelRule();

        if (null === $rule) {
            return [];
        }

        $ids = [];

        foreach ($rule->getCatalogPriceRuleCustomers() as $ruleCustomer) {
            $ids[] = (int) $ruleCustomer->getCustomerId();
        }

        return $ids;
    }

    public static function preloadCollection(array $resources): void
    {
        $ruleIds = [];

        foreach ($resources as $resource) {
            if ($resource instanceof self && null !== $resource->id) {
                $ruleIds[] = $resource->id;
            }
        }

        if ([] === $ruleIds) {
            return;
        }

        $statement = \Propel\Runtime\Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->query(\sprintf(
            'SELECT scope.catalog_price_rule_id AS rule_id, COUNT(DISTINCT pse.product_id) AS products, COUNT(*) AS sale_elements
             FROM `%s` scope
             INNER JOIN `product_sale_elements` pse ON pse.id = scope.product_sale_elements_id
             WHERE scope.catalog_price_rule_id IN (%s)
             GROUP BY scope.catalog_price_rule_id',
            CatalogPriceRuleProductSaleElementsTableMap::TABLE_NAME,
            implode(', ', array_map('intval', $ruleIds)),
        ));

        $coverageByRule = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $coverageByRule[(int) $row['rule_id']] = ['products' => (int) $row['products'], 'sale_elements' => (int) $row['sale_elements']];
        }

        foreach ($resources as $resource) {
            if ($resource instanceof self && null !== $resource->id) {
                $resource->preloadedCoverage = $coverageByRule[$resource->id] ?? ['products' => 0, 'sale_elements' => 0];
            }
        }
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CatalogPriceRuleTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return CatalogPriceRuleI18n::class;
    }

    /**
     * @return array{products: int, sale_elements: int}
     */
    private function coverage(): array
    {
        if (null !== $this->preloadedCoverage) {
            return $this->preloadedCoverage;
        }

        if (null === $this->id) {
            return ['products' => 0, 'sale_elements' => 0];
        }

        self::preloadCollection([$this]);

        return $this->preloadedCoverage ?? ['products' => 0, 'sale_elements' => 0];
    }

    private function propelRule(): ?CatalogPriceRuleModel
    {
        $propelModel = $this->getPropelModel();

        return $propelModel instanceof CatalogPriceRuleModel ? $propelModel : null;
    }
}
