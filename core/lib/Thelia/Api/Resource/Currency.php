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
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\NotInFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\CurrencyTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/currencies',
        ),
        new GetCollection(
            uriTemplate: '/admin/currencies',
        ),
        new Get(
            uriTemplate: '/admin/currencies/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/currencies/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/currencies/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/currencies/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/currencies',
        ),
        new Get(
            uriTemplate: '/front/currencies/{id}',
        ),
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
        'byDefault',
    ],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'code',
    ],
)]
#[ApiFilter(
    filterClass: NotInFilter::class,
    properties: [
        'id',
    ],
)]
class Currency extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:currency:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:currency:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:currency:write';

    #[Groups([
        self::GROUP_ADMIN_READ,
        Order::GROUP_ADMIN_READ,
        Cart::GROUP_ADMIN_READ_SINGLE,
        Cart::GROUP_FRONT_READ_SINGLE,
        ProductSaleElements::GROUP_ADMIN_READ_SINGLE,
        ProductPrice::GROUP_ADMIN_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_ADMIN_WRITE,
        ProductSaleElements::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_WRITE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?int $id = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        ProductSaleElements::GROUP_ADMIN_READ_SINGLE,
        Cart::GROUP_ADMIN_READ_SINGLE,
        Cart::GROUP_FRONT_READ_SINGLE,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $code = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Cart::GROUP_ADMIN_READ_SINGLE,
        Cart::GROUP_FRONT_READ_SINGLE,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $symbol = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $format = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?float $rate = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $visible = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $byDefault = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(?string $symbol): self
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(?float $rate): self
    {
        $this->rate = $rate;

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

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(?bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getByDefault(): ?bool
    {
        return $this->byDefault;
    }

    public function setByDefault(?bool $byDefault): self
    {
        $this->byDefault = $byDefault;

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
        return new CurrencyTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return CurrencyI18n::class;
    }
}
