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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\State\Provider\OrderPaymentTransactionCollectionProvider;
use Thelia\Model\Map\OrderPaymentTransactionTableMap;

/**
 * One money movement of an order's payment journal, read only: the journal is written
 * by the payment modules and by the capture operation, never by hand.
 *
 * Admin only. A customer sees whether the order is paid, which the order already
 * exposes; the movements behind it are the merchant's.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/orders/{orderId}/payment_transactions',
            uriVariables: ['orderId'],
            paginationItemsPerPage: 20,
            provider: OrderPaymentTransactionCollectionProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
class OrderPaymentTransaction implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_payment_transaction:read';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $type = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $state = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $amount = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $currencyId = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $pspReference = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $parentId = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $paymentModuleId = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $actorType = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $actorLabel = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $adminId = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $errorCode = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $errorMessage = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

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

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

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

    public function getCurrencyId(): ?int
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?int $currencyId): self
    {
        $this->currencyId = $currencyId;

        return $this;
    }

    public function getPspReference(): ?string
    {
        return $this->pspReference;
    }

    public function setPspReference(?string $pspReference): self
    {
        $this->pspReference = $pspReference;

        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getPaymentModuleId(): ?int
    {
        return $this->paymentModuleId;
    }

    public function setPaymentModuleId(?int $paymentModuleId): self
    {
        $this->paymentModuleId = $paymentModuleId;

        return $this;
    }

    public function getActorType(): ?string
    {
        return $this->actorType;
    }

    public function setActorType(?string $actorType): self
    {
        $this->actorType = $actorType;

        return $this;
    }

    public function getActorLabel(): ?string
    {
        return $this->actorLabel;
    }

    public function setActorLabel(?string $actorLabel): self
    {
        $this->actorLabel = $actorLabel;

        return $this;
    }

    public function getAdminId(): ?int
    {
        return $this->adminId;
    }

    public function setAdminId(?int $adminId): self
    {
        $this->adminId = $adminId;

        return $this;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): self
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new OrderPaymentTransactionTableMap();
    }
}
