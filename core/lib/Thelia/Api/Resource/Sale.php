<?php

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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/sales'
        ),
        new GetCollection(
            uriTemplate: '/admin/sales'
        ),
        new Get(
            uriTemplate: '/admin/sales/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/sales/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/sales/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Sale extends AbstractTranslatableResource
{
    public const GROUP_READ = 'sale:read';
    public const GROUP_READ_SINGLE = 'sale:read:single';
    public const GROUP_WRITE = 'sale:write';

    #[Groups([self::GROUP_READ, SaleProduct::GROUP_READ_SINGLE, SaleOffsetCurrency::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, SaleProduct::GROUP_READ_SINGLE, SaleOffsetCurrency::GROUP_READ_SINGLE])]
    public bool $active;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, SaleProduct::GROUP_READ_SINGLE, SaleOffsetCurrency::GROUP_READ_SINGLE])]
    public bool $displayInitialPrice;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, SaleProduct::GROUP_READ_SINGLE, SaleOffsetCurrency::GROUP_READ_SINGLE])]
    public ?\DateTime $startDate;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, SaleProduct::GROUP_READ_SINGLE, SaleOffsetCurrency::GROUP_READ_SINGLE])]
    public ?\DateTime $endDate;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, SaleProduct::GROUP_READ_SINGLE, SaleOffsetCurrency::GROUP_READ_SINGLE])]
    public int $priceOffsetType;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isDisplayInitialPrice(): bool
    {
        return $this->displayInitialPrice;
    }

    public function setDisplayInitialPrice(bool $displayInitialPrice): self
    {
        $this->displayInitialPrice = $displayInitialPrice;

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

    public function getPriceOffsetType(): int
    {
        return $this->priceOffsetType;
    }

    public function setPriceOffsetType(int $priceOffsetType): self
    {
        $this->priceOffsetType = $priceOffsetType;

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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Sale::class;
    }

    public static function getI18nResourceClass(): string
    {
        return SaleI18n::class;
    }
}
