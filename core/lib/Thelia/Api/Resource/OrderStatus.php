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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Model\Map\OrderStatusTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_statutes'
        ),
        new GetCollection(
            uriTemplate: '/admin/order_statutes'
        ),
        new Get(
            uriTemplate: '/admin/order_statutes/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_statutes/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_statutes/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
// todo add filters
class OrderStatus extends AbstractTranslatableResource
{
    public const GROUP_READ = 'order_status:read';
    public const GROUP_READ_SINGLE = 'order_status:read:single';
    public const GROUP_WRITE = 'order_status:write';

    #[Groups([self::GROUP_READ, Order::GROUP_READ, Order::GROUP_WRITE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public string $code;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?string $color;

    #[Groups([self::GROUP_READ])]
    public ?int $position;

    #[Groups([self::GROUP_READ])]
    public ?bool $protectedStatus;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

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

    public function getProtectedStatus(): ?bool
    {
        return $this->protectedStatus;
    }

    public function setProtectedStatus(?bool $protectedStatus): self
    {
        $this->protectedStatus = $protectedStatus;

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
        return new OrderStatusTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return OrderStatusI18n::class;
    }
}
