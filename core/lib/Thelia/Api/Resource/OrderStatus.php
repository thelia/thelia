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
            uriTemplate: '/admin/order_status'
        ),
        new GetCollection(
            uriTemplate: '/admin/order_status'
        ),
        new Get(
            uriTemplate: '/admin/order_status/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_status/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_status/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
class OrderStatus extends AbstractTranslatableResource
{
    public const GROUP_READ = 'order_status:read';
    public const GROUP_READ_SINGLE = 'order_status:read:single';
    public const GROUP_WRITE = 'order_status:write';

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public string $code;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?string $color;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\OrderStatus::class;
    }

    public static function getI18nResourceClass(): string
    {
        return OrderStatusI18n::class;
    }
}
