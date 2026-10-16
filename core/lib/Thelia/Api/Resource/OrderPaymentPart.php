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
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\OrderPaymentPartTableMap;

/**
 * One share of what an order is paid with: a payment module, the amount it carries
 * and where the share stands. Read only, parts are written by the checkout and by
 * the payment modules, never through the API.
 *
 * The customer reads the parts of their own order through the order itself; there
 * is no front resource of its own. On the admin side the collection is filtered by
 * `order.id`.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/order_payment_parts',
        ),
        new Get(
            uriTemplate: '/admin/order_payment_parts/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'state' => 'exact',
        'order.id' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'position',
        'createdAt',
    ],
)]
class OrderPaymentPart implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_payment_part:read';

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_FRONT_READ_SINGLE])]
    public ?int $id = null;

    #[Relation(targetResource: Order::class)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?Order $order = null;

    /**
     * Decimal read as a string, so a share never goes through a float.
     */
    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_FRONT_READ_SINGLE])]
    public ?string $amount = null;

    /**
     * pending, settled, failed or released.
     */
    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_FRONT_READ_SINGLE])]
    public ?string $state = null;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_FRONT_READ_SINGLE])]
    public ?bool $isPrimary = null;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_FRONT_READ_SINGLE])]
    public ?int $position = null;

    /**
     * The module the share is paid with. Null once the module is deleted: the name
     * it carried then stays in paymentModuleTitle.
     */
    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE])]
    public ?Module $paymentModule = null;

    /**
     * The name the module had when it was deleted, null while it is installed.
     */
    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_FRONT_READ_SINGLE])]
    public ?string $paymentModuleTitle = null;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getIsPrimary(): ?bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(?bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;

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

    public function getPaymentModule(): ?Module
    {
        return $this->paymentModule;
    }

    public function setPaymentModule(?Module $paymentModule): self
    {
        $this->paymentModule = $paymentModule;

        return $this;
    }

    public function getPaymentModuleTitle(): ?string
    {
        return $this->paymentModuleTitle;
    }

    public function setPaymentModuleTitle(?string $paymentModuleTitle): self
    {
        $this->paymentModuleTitle = $paymentModuleTitle;

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
        return new OrderPaymentPartTableMap();
    }
}
