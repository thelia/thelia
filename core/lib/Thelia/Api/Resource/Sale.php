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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\SaleTableMap;
use Thelia\Model\Sale as SaleModel;
use Thelia\Model\SaleProductQuery;
use Thelia\Model\Tools\UrlRewritingTrait;

/**
 * A sale operation, read only.
 *
 * The front block is what a theme builds the page of an operation from: its
 * texts, its window, the products it covers, and whether a countdown to its end
 * is due right now. The countdown is answered as a number of seconds rather than
 * as a date so that a theme has nothing to work out — and nothing to get wrong
 * about the shop's timezone.
 *
 * What is deliberately absent, in the front groups and everywhere near them: the
 * customers a reserved operation is open to. That list is the operation's private
 * guest list, and a customer reading their own operation must not be handed the
 * names of the others.
 *
 * Nothing writes through here. The back office drives an operation through its
 * events — they are what recomputes the catalog prices of a public one — and a
 * write endpoint would let a caller change an operation without any of that
 * happening.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/sales',
        ),
        new Get(
            uriTemplate: '/admin/sales/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/sales',
        ),
        new Get(
            uriTemplate: '/front/sales/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
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
        'startDate',
        'endDate',
    ],
)]
class Sale extends AbstractTranslatableResource implements CollectionPreloadableInterface
{
    use UrlRewritingTrait;

    public const GROUP_ADMIN_READ = 'admin:sale:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:sale:read:single';
    public const GROUP_FRONT_READ = 'front:sale:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:sale:read:single';

    /**
     * The product ids read for the whole page, when this resource is part of one.
     *
     * @var list<int>|null
     */
    private ?array $preloadedProductIds = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?bool $active = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $startDate = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $endDate = null;

    /** Whether the theme is asked to strike the catalog price through next to the discounted one. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?bool $displayInitialPrice = null;

    /** One of the SaleModel::OFFSET_TYPE_* constants. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $priceOffsetType = null;

    /** One of the SaleModel::AUDIENCE_MODE_* constants. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $audienceMode = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?bool $hideProducts = null;

    /** One of the SaleModel::COUNTDOWN_MODE_* constants. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $countdownMode = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $countdownLeadHours = null;

    #[ApiProperty(types: 'object')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
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

    public function getDisplayInitialPrice(): ?bool
    {
        return $this->displayInitialPrice;
    }

    public function setDisplayInitialPrice(?bool $displayInitialPrice): self
    {
        $this->displayInitialPrice = $displayInitialPrice;

        return $this;
    }

    public function getPriceOffsetType(): ?int
    {
        return $this->priceOffsetType;
    }

    public function setPriceOffsetType(?int $priceOffsetType): self
    {
        $this->priceOffsetType = $priceOffsetType;

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

    public function getHideProducts(): ?bool
    {
        return $this->hideProducts;
    }

    public function setHideProducts(?bool $hideProducts): self
    {
        $this->hideProducts = $hideProducts;

        return $this;
    }

    public function getCountdownMode(): ?int
    {
        return $this->countdownMode;
    }

    public function setCountdownMode(?int $countdownMode): self
    {
        $this->countdownMode = $countdownMode;

        return $this;
    }

    public function getCountdownLeadHours(): ?int
    {
        return $this->countdownLeadHours;
    }

    public function setCountdownLeadHours(?int $countdownLeadHours): self
    {
        $this->countdownLeadHours = $countdownLeadHours;

        return $this;
    }

    /**
     * Whether the countdown to the end of the operation is due right now — which
     * depends on the mode, on the end date, and on the instant being asked about.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getShouldDisplayCountdown(): bool
    {
        return $this->propelSale()?->shouldDisplayCountdown(new \DateTime()) ?? false;
    }

    /**
     * How long the operation still has to run, in seconds, or null when no countdown
     * is due. Never negative: an operation whose end date has passed has nothing
     * left to count down, and a theme reading a negative number would render it.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getCountdownRemainingSeconds(): ?int
    {
        $sale = $this->propelSale();

        if (null === $sale || !$sale->shouldDisplayCountdown($now = new \DateTime())) {
            return null;
        }

        $endDate = $sale->getEndDate();

        if (null === $endDate) {
            return null;
        }

        return max(0, $endDate->getTimestamp() - $now->getTimestamp());
    }

    /**
     * The products the operation covers, so a theme lays out its grid from one read
     * instead of querying the catalog for the operation first.
     *
     * On a page of operations the answer is already in hand — see
     * {@see preloadCollection()} — and only an operation read on its own goes to the
     * database for it.
     *
     * @return list<int>
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getProductIds(): array
    {
        if (null !== $this->preloadedProductIds) {
            return $this->preloadedProductIds;
        }

        $sale = $this->propelSale();

        if (null === $sale) {
            return [];
        }

        $productIds = [];

        foreach ($sale->getSaleProductList() as $saleProduct) {
            $productIds[] = (int) $saleProduct->getProductId();
        }

        return $productIds;
    }

    /**
     * Reads the products of every operation of a page in one statement.
     *
     * `productIds` belongs to both read groups, so the serializer asks each member of
     * a collection for it, and each answer used to be a `sale_product` select of its
     * own: a shop listing thirty operations paid thirty statements for a field its
     * theme reads on every one of them.
     *
     * @param list<PropelResourceInterface> $resources
     */
    public static function preloadCollection(array $resources): void
    {
        $saleIds = [];

        foreach ($resources as $resource) {
            if ($resource instanceof self && null !== $resource->id) {
                $saleIds[] = $resource->id;
            }
        }

        if ([] === $saleIds) {
            return;
        }

        // One row per (operation, product): an operation covering a product through
        // several attribute values holds several rows for it, and the field is a list
        // of products.
        $rows = SaleProductQuery::create()
            ->filterBySaleId($saleIds, Criteria::IN)
            ->select(['SaleId', 'ProductId'])
            ->distinct()
            ->orderBySaleId()
            ->orderByProductId()
            ->find()
            ->getData();

        $productIdsBySale = [];

        foreach ($rows as $row) {
            $productIdsBySale[(int) $row['SaleId']][] = (int) $row['ProductId'];
        }

        foreach ($resources as $resource) {
            if ($resource instanceof self && null !== $resource->id) {
                $resource->preloadedProductIds = $productIdsBySale[$resource->id] ?? [];
            }
        }
    }

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getPublicUrl(): ?string
    {
        $sale = $this->propelSale();

        if (null === $sale) {
            return null;
        }

        return $this->getUrl($sale->getLocale() ?: $this->getDefaultLocale());
    }

    public function getRewrittenUrlViewName(): string
    {
        return $this->propelSale()?->getRewrittenUrlViewName() ?: '';
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new SaleTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return SaleI18n::class;
    }

    private function propelSale(): ?SaleModel
    {
        $propelModel = $this->getPropelModel();

        return $propelModel instanceof SaleModel ? $propelModel : null;
    }
}
