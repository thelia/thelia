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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\PriceListTableMap;
use Thelia\Model\PriceListQuery;

/**
 * A negotiated price list: the prices (or discounts) its lines give to the sale elements,
 * for the customers and groups it is assigned to, in one currency, over a period.
 *
 * Administration only: a negotiated price never leaves through the storefront API, the front
 * reads the price a customer pays on the sale element itself.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/price_lists'),
        new GetCollection(uriTemplate: '/admin/price_lists'),
        new Get(uriTemplate: '/admin/price_lists/{id}'),
        new Put(uriTemplate: '/admin/price_lists/{id}'),
        new Patch(uriTemplate: '/admin/price_lists/{id}'),
        new Delete(uriTemplate: '/admin/price_lists/{id}'),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'code' => 'exact',
        'currency.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'price_list.currency_id',
        ],
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'active',
        'exclusive',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'priority',
        'code',
    ],
)]
class PriceList extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:price_list:read';
    public const GROUP_ADMIN_WRITE = 'admin:price_list:write';

    public const CODE_PATTERN = '/^[A-Za-z0-9._-]{1,64}$/';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Length(max: 64, groups: [self::GROUP_ADMIN_WRITE])]
    #[Regex(
        pattern: self::CODE_PATTERN,
        message: 'The code is made of letters, digits, dots, dashes and underscores, 64 characters at most.',
        groups: [self::GROUP_ADMIN_WRITE],
    )]
    public ?string $code = null;

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public Currency $currency;

    /**
     * Lowest first: the list with the smallest priority is looked up first.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $priority = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $exclusive = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?\DateTime $startsAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?\DateTime $endsAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $active = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public I18nCollection $i18ns;

    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validatePeriod(ExecutionContextInterface $context): void
    {
        if (null !== $this->startsAt && null !== $this->endsAt && $this->startsAt >= $this->endsAt) {
            $context->buildViolation('The list must start before it ends.')
                ->atPath('endsAt')
                ->addViolation();
        }
    }

    /**
     * The code is unique: a duplicate is a violation on the field, not the failed write of the
     * unique index.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateCodeUniqueness(ExecutionContextInterface $context): void
    {
        if (null === $this->code || '' === $this->code) {
            return;
        }

        $query = PriceListQuery::create()->filterByCode($this->code);

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This code is already used by another price list.')
                ->atPath('code')
                ->addViolation();
        }
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

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): self
    {
        $this->currency = $currency;

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

    public function getExclusive(): ?bool
    {
        return $this->exclusive;
    }

    public function setExclusive(?bool $exclusive): self
    {
        $this->exclusive = $exclusive;

        return $this;
    }

    public function getStartsAt(): ?\DateTime
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTime $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTime
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTime $endsAt): self
    {
        $this->endsAt = $endsAt;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new PriceListTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return PriceListI18n::class;
    }
}
