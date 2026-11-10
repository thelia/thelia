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
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\SubscriptionActionProcessor;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\SubscriptionTableMap;
use Thelia\Model\Subscription as SubscriptionModel;
use Thelia\Model\SubscriptionOrderQuery;

/**
 * What a customer bought by subscription, and where it stands.
 *
 * The front reads and acts on its own subscriptions only: SubscriptionOwnershipExtension
 * scopes every front operation to the customer of the token, so another customer's id
 * answers 404 like a missing one. The actions are POSTs on the subscription
 * (cancel, pause, resume, payment-method); each goes through SubscriptionManager, which
 * refuses what the state of the subscription does not allow with a 409. The payment method
 * is only ever shown as its label, never its token. The administration reads every
 * subscription, filtered by status; what it does to one is done in the back office.
 *
 * The values of the plan are the ones frozen when the subscription was taken.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/subscriptions',
        ),
        new Get(
            uriTemplate: '/admin/subscriptions/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/subscriptions',
            security: self::FRONT_SECURITY,
        ),
        new Get(
            uriTemplate: '/front/account/subscriptions/{id}',
            security: self::FRONT_SECURITY,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
        new Post(
            uriTemplate: '/front/account/subscriptions/{id}/cancel',
            status: 200,
            security: self::FRONT_SECURITY,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            read: true,
            deserialize: false,
            processor: SubscriptionActionProcessor::class,
            extraProperties: ['subscription_action' => SubscriptionActionProcessor::CANCEL],
        ),
        new Post(
            uriTemplate: '/front/account/subscriptions/{id}/pause',
            status: 200,
            security: self::FRONT_SECURITY,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            read: true,
            deserialize: false,
            processor: SubscriptionActionProcessor::class,
            extraProperties: ['subscription_action' => SubscriptionActionProcessor::PAUSE],
        ),
        new Post(
            uriTemplate: '/front/account/subscriptions/{id}/resume',
            status: 200,
            security: self::FRONT_SECURITY,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            read: true,
            deserialize: false,
            processor: SubscriptionActionProcessor::class,
            extraProperties: ['subscription_action' => SubscriptionActionProcessor::RESUME],
        ),
        new Post(
            uriTemplate: '/front/account/subscriptions/{id}/payment_method',
            status: 200,
            security: self::FRONT_SECURITY,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            read: true,
            deserialize: false,
            processor: SubscriptionActionProcessor::class,
            extraProperties: ['subscription_action' => SubscriptionActionProcessor::PAYMENT_METHOD],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'ref',
        'status',
        'customer.id',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'nextBillingAt',
        'createdAt',
    ],
)]
class Subscription implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:subscription:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:subscription:read:single';
    public const GROUP_FRONT_READ = 'front:subscription:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:subscription:read:single';

    public const FRONT_SECURITY = 'is_granted("ROLE_CUSTOMER")';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $ref = null;

    /** One of trial, active, payment_failed, suspended, paused, cancelled, ended. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $status = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $quantity = null;

    /** week, month, quarter or year. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $periodUnit = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $periodCount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $installmentCount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $commitmentCount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?bool $pausable = null;

    /** What one installment costs for one unit, untaxed, frozen when the subscription was taken. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $unitPrice = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $installmentsBilled = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $currentPeriodStart = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $currentPeriodEnd = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $nextBillingAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $cancelAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $pausedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $nextRetryAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $failureCount = null;

    // Admin only: the front is scoped to its owner by SubscriptionOwnershipExtension.
    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public Customer $customer;

    /** The code of the currency of the installments. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $currencyCode = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?int $productId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $productRef = null;

    /** The title of the product in the language the subscription was taken in. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $productTitle = null;

    /** The method the installments are charged to, as the customer sees it ("Visa ending 4242"): never its token. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $paymentMethodLabel = null;

    /** The id of that method, to match it with /front/account/payment_methods. */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?int $paymentMethodId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?int $initialOrderId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $initialOrderRef = null;

    /**
     * One entry per installment, in order: number, state (pending, paid or failed), orderId,
     * orderRef, date (the day of the order) and attemptCount; the administration also gets
     * lastError. Read on a single subscription only.
     *
     * @var list<array<string, mixed>>
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    #[ApiProperty(readable: true, writable: false)]
    public array $installments = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getPeriodUnit(): ?string
    {
        return $this->periodUnit;
    }

    public function setPeriodUnit(?string $periodUnit): self
    {
        $this->periodUnit = $periodUnit;

        return $this;
    }

    public function getPeriodCount(): ?int
    {
        return $this->periodCount;
    }

    public function setPeriodCount(?int $periodCount): self
    {
        $this->periodCount = $periodCount;

        return $this;
    }

    public function getInstallmentCount(): ?int
    {
        return $this->installmentCount;
    }

    public function setInstallmentCount(?int $installmentCount): self
    {
        $this->installmentCount = $installmentCount;

        return $this;
    }

    public function getCommitmentCount(): ?int
    {
        return $this->commitmentCount;
    }

    public function setCommitmentCount(?int $commitmentCount): self
    {
        $this->commitmentCount = $commitmentCount;

        return $this;
    }

    public function getPausable(): ?bool
    {
        return $this->pausable;
    }

    public function setPausable(?bool $pausable): self
    {
        $this->pausable = $pausable;

        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getInstallmentsBilled(): ?int
    {
        return $this->installmentsBilled;
    }

    public function setInstallmentsBilled(?int $installmentsBilled): self
    {
        $this->installmentsBilled = $installmentsBilled;

        return $this;
    }

    public function getCurrentPeriodStart(): ?\DateTime
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(?\DateTime $currentPeriodStart): self
    {
        $this->currentPeriodStart = $currentPeriodStart;

        return $this;
    }

    public function getCurrentPeriodEnd(): ?\DateTime
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(?\DateTime $currentPeriodEnd): self
    {
        $this->currentPeriodEnd = $currentPeriodEnd;

        return $this;
    }

    public function getNextBillingAt(): ?\DateTime
    {
        return $this->nextBillingAt;
    }

    public function setNextBillingAt(?\DateTime $nextBillingAt): self
    {
        $this->nextBillingAt = $nextBillingAt;

        return $this;
    }

    public function getCancelAt(): ?\DateTime
    {
        return $this->cancelAt;
    }

    public function setCancelAt(?\DateTime $cancelAt): self
    {
        $this->cancelAt = $cancelAt;

        return $this;
    }

    public function getPausedAt(): ?\DateTime
    {
        return $this->pausedAt;
    }

    public function setPausedAt(?\DateTime $pausedAt): self
    {
        $this->pausedAt = $pausedAt;

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

    public function getNextRetryAt(): ?\DateTime
    {
        return $this->nextRetryAt;
    }

    public function setNextRetryAt(?\DateTime $nextRetryAt): self
    {
        $this->nextRetryAt = $nextRetryAt;

        return $this;
    }

    public function getFailureCount(): ?int
    {
        return $this->failureCount;
    }

    public function setFailureCount(?int $failureCount): self
    {
        $this->failureCount = $failureCount;

        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function afterModelToResource(array $context): void
    {
        $model = $this->getPropelModel();

        if (!$model instanceof SubscriptionModel) {
            return;
        }

        $this->currencyCode = CurrencyQuery::create()->findPk($model->getCurrencyId())?->getCode();
        $this->initialOrderId = $model->getInitialOrderId();
        $this->initialOrderRef = $model->getInitialOrder()?->getRef();
        $this->paymentMethodId = $model->getCustomerPaymentMethodId();
        // Only the label: the token of the method has no place in this resource.
        $this->paymentMethodLabel = $model->getCustomerPaymentMethod()?->getLabel();

        $saleElements = $model->getProductSaleElements();
        $product = $saleElements?->getProduct();

        if (null !== $product) {
            $this->productId = $product->getId();
            $this->productRef = $product->getRef();
            $this->productTitle = $product->setLocale((string) LangQuery::create()->findPk($model->getLangId())?->getLocale())->getTitle();
        }

        $groups = $context['groups'] ?? [];
        $groups = \is_array($groups) ? $groups : [$groups];
        $admin = \in_array(self::GROUP_ADMIN_READ_SINGLE, $groups, true);

        if (!$admin && !\in_array(self::GROUP_FRONT_READ_SINGLE, $groups, true)) {
            return;
        }

        $this->installments = [];

        foreach (SubscriptionOrderQuery::create()->filterBySubscriptionId($model->getId())->orderByInstallmentNumber()->find() as $installment) {
            $order = $installment->getOrder();
            $entry = [
                'number' => (int) $installment->getInstallmentNumber(),
                'state' => $installment->getState(),
                'orderId' => $installment->getOrderId(),
                'orderRef' => $order?->getRef(),
                'date' => $order?->getCreatedAt('Y-m-d'),
                'attemptCount' => (int) $installment->getAttemptCount(),
            ];

            if ($admin) {
                $entry['lastError'] = $installment->getLastError();
            }

            $this->installments[] = $entry;
        }
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new SubscriptionTableMap();
    }
}
