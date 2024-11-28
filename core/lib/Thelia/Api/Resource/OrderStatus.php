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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
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
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/admin/order_statutes/code/{code}',
            uriVariables: [
                'code' => new Link(
                    fromProperty: 'code',
                    fromClass: self::class
                ),
            ],
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_statutes/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_statutes/{id}'
        ),
    ],
    uriVariables: [
        'id' => new Link(
            fromClass: self::class,
            identifiers: ['id']
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
class OrderStatus extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:order_status:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order_status:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order_status:write';

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    public ?int $id = null;

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, Order::GROUP_ADMIN_READ])]
    public string $code;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, Order::GROUP_ADMIN_READ])]
    public ?string $color = '#c3c3c3';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $position;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $protectedStatus = false;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt;

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
