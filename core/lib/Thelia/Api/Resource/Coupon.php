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
use DateTime;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\DateFilter;
use Thelia\Api\Bridge\Propel\Filter\NotInFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;
use Thelia\Model\Map\CouponTableMap;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/coupons',
        ),
        new Get(
            uriTemplate: '/front/coupons/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
        new Get(
            uriTemplate: '/front/coupons/{code}/by-code',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'code',
        'type',
        'title' => 'partial',
    ],
)]
#[ApiFilter(
    filterClass: NotInFilter::class,
    properties: [
        'id',
        'code',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'isEnabled',
        'isCumulative',
        'isRemovingPostage',
        'isAvailableOnSpecialOffers',
        'isUsed',
        'perCustomerUsageCount',
    ],
)]
#[ApiFilter(
    filterClass: DateFilter::class,
    properties: [
        'startDate',
        'expirationDate',
        'createdAt',
        'updatedAt',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'code',
        'title',
        'isEnabled',
        'startDate',
        'expirationDate',
        'maxUsage',
        'createdAt',
        'updatedAt',
    ],
)]
class Coupon extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:coupon:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:coupon:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:coupon:write';
    public const GROUP_FRONT_READ = 'front:coupon:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:coupon:read:single';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $code = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $type = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $serializedEffects = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $isEnabled = true;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ?DateTime $startDate = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ?DateTime $expirationDate = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public int $maxUsage = 0;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $isCumulative = true;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $isRemovingPostage = false;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $isAvailableOnSpecialOffers = false;

    #[Groups([self::GROUP_ADMIN_READ])]
    public bool $isUsed = false;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $serializedConditions = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public bool $perCustomerUsageCount = false;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public I18nCollection $i18ns;

    #[Relation(targetResource: CouponCountry::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public array $couponCountries = [];

    #[Relation(targetResource: CouponModule::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public array $couponModules = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSerializedEffects(): ?string
    {
        return $this->serializedEffects;
    }

    public function setSerializedEffects(?string $serializedEffects): self
    {
        $this->serializedEffects = $serializedEffects;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?DateTime $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getExpirationDate(): ?DateTime
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?DateTime $expirationDate): self
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    public function getMaxUsage(): int
    {
        return $this->maxUsage;
    }

    public function setMaxUsage(int $maxUsage): self
    {
        $this->maxUsage = $maxUsage;

        return $this;
    }

    public function isCumulative(): bool
    {
        return $this->isCumulative;
    }

    public function setIsCumulative(bool $isCumulative): self
    {
        $this->isCumulative = $isCumulative;

        return $this;
    }

    public function isRemovingPostage(): bool
    {
        return $this->isRemovingPostage;
    }

    public function setIsRemovingPostage(bool $isRemovingPostage): self
    {
        $this->isRemovingPostage = $isRemovingPostage;

        return $this;
    }

    public function isAvailableOnSpecialOffers(): bool
    {
        return $this->isAvailableOnSpecialOffers;
    }

    public function setIsAvailableOnSpecialOffers(bool $isAvailableOnSpecialOffers): self
    {
        $this->isAvailableOnSpecialOffers = $isAvailableOnSpecialOffers;

        return $this;
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function setIsUsed(bool $isUsed): self
    {
        $this->isUsed = $isUsed;

        return $this;
    }

    public function getSerializedConditions(): ?string
    {
        return $this->serializedConditions;
    }

    public function setSerializedConditions(?string $serializedConditions): self
    {
        $this->serializedConditions = $serializedConditions;

        return $this;
    }

    public function isPerCustomerUsageCount(): bool
    {
        return $this->perCustomerUsageCount;
    }

    public function setPerCustomerUsageCount(bool $perCustomerUsageCount): self
    {
        $this->perCustomerUsageCount = $perCustomerUsageCount;

        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCouponCountries(): array
    {
        return $this->couponCountries;
    }

    public function setCouponCountries(array $couponCountries): self
    {
        $this->couponCountries = $couponCountries;

        return $this;
    }

    public function getCouponModules(): array
    {
        return $this->couponModules;
    }

    public function setCouponModules(array $couponModules): self
    {
        $this->couponModules = $couponModules;

        return $this;
    }

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public function getDaysLeftBeforeExpiration(): int
    {
        if ($this->expirationDate === null) {
            return 0;
        }

        $now = new DateTime();
        $diff = $now->diff($this->expirationDate);

        return max(0, $diff->days);
    }

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public function getUsageLeft(): int
    {
        return max(0, $this->maxUsage);
    }

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public function getFreeShippingForCountriesList(): string
    {
        $countryIds = [];
        foreach ($this->couponCountries as $couponCountry) {
            $countryIds[] = $couponCountry->getCountryId();
        }

        return implode(',', $countryIds);
    }

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public function getFreeShippingForModulesList(): string
    {
        $moduleIds = [];
        foreach ($this->couponModules as $couponModule) {
            $moduleIds[] = $couponModule->getModuleId();
        }

        return implode(',', $moduleIds);
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CouponTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return CouponI18n::class;
    }
}
