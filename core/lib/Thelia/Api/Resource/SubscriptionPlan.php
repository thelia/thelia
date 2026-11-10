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
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\SubscriptionPlanProcessor;
use Thelia\Domain\Subscription\Enum\PeriodUnit;
use Thelia\Model\Map\SubscriptionPlanTableMap;
use Thelia\Model\SubscriptionPlan as SubscriptionPlanModel;
use Thelia\Model\SubscriptionPlanPriceQuery;

/**
 * How a product is sold by subscription: the period an installment pays for, the trial,
 * the end of the subscription, the commitment, and what an installment costs per currency.
 *
 * The administration writes plans, with their prices (`prices`: one entry per currency,
 * `{"currencyId": 1, "price": 9.9, "fromDefaultCurrency": false}`; a list that is sent
 * replaces the prices of the plan, one that is left out leaves them alone). Editing a plan
 * changes nothing for a subscription already running: it holds a copy of the values.
 *
 * The front lists the visible plans of a product (`?product.id=12`): title, period, trial,
 * commitment, and the price of one installment, untaxed, in the currency being browsed
 * (`currency` parameter, the default currency otherwise). A plan that is not visible is
 * never read there.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/subscription_plans',
            processor: SubscriptionPlanProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/subscription_plans',
        ),
        new Get(
            uriTemplate: '/admin/subscription_plans/{id}',
        ),
        new Put(
            uriTemplate: '/admin/subscription_plans/{id}',
            processor: SubscriptionPlanProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/subscription_plans/{id}',
            processor: SubscriptionPlanProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/subscription_plans/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/subscription_plans',
        ),
        new Get(
            uriTemplate: '/front/subscription_plans/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'product.id',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
        'pausable',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ],
)]
class SubscriptionPlan extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:subscription_plan:read';
    public const GROUP_ADMIN_WRITE = 'admin:subscription_plan:write';
    public const GROUP_FRONT_READ = 'front:subscription_plan:read';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    public Product $product;

    /** week, month, quarter or year. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[Choice(callback: [PeriodUnit::class, 'values'])]
    public string $periodUnit = 'month';

    /** How many period units an installment pays for. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[Positive]
    public int $periodCount = 1;

    /** Days served before the first charge; 0: no trial. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[PositiveOrZero]
    public int $trialDays = 0;

    /** How many installments are billed in total; null: until the subscription is cancelled. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[Positive]
    public ?int $installmentCount = null;

    /** How many installments the customer commits to; null: none. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[Positive]
    public ?int $commitmentCount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public bool $pausable = false;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public bool $visible = true;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public I18nCollection $i18ns;

    /**
     * Administration: the price of one installment per currency, as written. Null on a write
     * that does not touch the prices.
     *
     * @var list<array{currencyId: int, price: float|string, fromDefaultCurrency?: bool, currencyCode?: string}>|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[ApiProperty(readable: true, writable: true)]
    public ?array $prices = null;

    /** Front: the id of the product the plan sells. */
    #[Groups([self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?int $productId = null;

    /** Front: the price of one installment, untaxed, in the currency being browsed. */
    #[Groups([self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?float $price = null;

    /** Front: the code of that currency. */
    #[Groups([self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $currencyCode = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getPeriodUnit(): string
    {
        return $this->periodUnit;
    }

    public function setPeriodUnit(string $periodUnit): self
    {
        $this->periodUnit = $periodUnit;

        return $this;
    }

    public function getPeriodCount(): int
    {
        return $this->periodCount;
    }

    public function setPeriodCount(int $periodCount): self
    {
        $this->periodCount = $periodCount;

        return $this;
    }

    public function getTrialDays(): int
    {
        return $this->trialDays;
    }

    public function setTrialDays(int $trialDays): self
    {
        $this->trialDays = $trialDays;

        return $this;
    }

    public function getInstallmentCount(): ?int
    {
        return $this->installmentCount;
    }

    public function setInstallmentCount(?int $installmentCount): self
    {
        $this->installmentCount = $installmentCount;

        return $this;
    }

    public function getCommitmentCount(): ?int
    {
        return $this->commitmentCount;
    }

    public function setCommitmentCount(?int $commitmentCount): self
    {
        $this->commitmentCount = $commitmentCount;

        return $this;
    }

    public function getPausable(): bool
    {
        return $this->pausable;
    }

    public function setPausable(bool $pausable): self
    {
        $this->pausable = $pausable;

        return $this;
    }

    public function getVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return list<array{currencyId: int, price: float|string, fromDefaultCurrency?: bool, currencyCode?: string}>|null
     */
    public function getPrices(): ?array
    {
        return $this->prices;
    }

    /**
     * @param list<array{currencyId: int, price: float|string, fromDefaultCurrency?: bool, currencyCode?: string}>|null $prices
     */
    public function setPrices(?array $prices): self
    {
        $this->prices = $prices;

        return $this;
    }

    public function afterModelToResource(array $context): void
    {
        $model = $this->getPropelModel();

        if (!$model instanceof SubscriptionPlanModel) {
            return;
        }

        $this->productId = $model->getProductId();

        $groups = $context['groups'] ?? [];
        $groups = \is_array($groups) ? $groups : [$groups];

        if (!\in_array(self::GROUP_ADMIN_READ, $groups, true)) {
            return;
        }

        $this->prices = [];

        foreach (SubscriptionPlanPriceQuery::create()->filterBySubscriptionPlanId($model->getId())->orderByCurrencyId()->find() as $price) {
            $this->prices[] = [
                'currencyId' => (int) $price->getCurrencyId(),
                'currencyCode' => $price->getCurrency()?->getCode(),
                'price' => (float) $price->getPrice(),
                'fromDefaultCurrency' => 1 === (int) $price->getFromDefaultCurrency(),
            ];
        }
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new SubscriptionPlanTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return SubscriptionPlanI18n::class;
    }
}
