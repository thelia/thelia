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

namespace Thelia\Domain\Webhook\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Api\Resource\Product as ProductResource;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateMinimalEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Webhook\ProductChangeFingerprint;
use Thelia\Domain\Webhook\WebhookPublisher;
use Thelia\Log\Tlog;
use Thelia\Model\OrderStatusQuery;

readonly class PublishWebhookEventsListener
{
    /** @var \WeakMap<ProductUpdateEvent, string> */
    private \WeakMap $productBeforeUpdate;

    public function __construct(
        private WebhookPublisher $publisher,
        private ProductChangeFingerprint $productFingerprint,
    ) {
        $this->productBeforeUpdate = new \WeakMap();
    }

    #[AsEventListener(event: TheliaEvents::ORDER_BEFORE_PAYMENT, priority: 32)]
    public function onOrderPlaced(OrderEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $this->publisher->publish('order.created', $event->getOrder());
        });
    }

    #[AsEventListener(event: TheliaEvents::ORDER_CREATE_MANUAL, priority: 32)]
    public function onOrderCreatedManually(OrderManualEvent $event): void
    {
        $this->safely(function () use ($event): void {
            if (!$event->hasPlacedOrder()) {
                return;
            }

            $this->publisher->publish('order.created', $event->getPlacedOrder());
        });
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 32)]
    public function onOrderStatusUpdated(OrderEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $previousStatusId = $event->getPreviousStatusId();

            if (null === $previousStatusId || $previousStatusId === $event->getStatus()) {
                return;
            }

            $order = $event->getOrder();
            $this->publisher->publish('order.status_changed', $order);

            $wasPaid = OrderStatusQuery::create()->findPk($previousStatusId)?->isPaid(false) ?? false;

            if (!$wasPaid && $order->getOrderStatus()?->isPaid(false)) {
                $this->publisher->publish('order.paid', $order);
            }
        });
    }

    #[AsEventListener(event: TheliaEvents::CUSTOMER_CREATEACCOUNT, priority: 32)]
    public function onCustomerCreated(CustomerCreateOrUpdateEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $this->publisher->publish('customer.created', $event->getCustomer());
        });
    }

    #[AsEventListener(event: TheliaEvents::CREATE_CUSTOMER_MINIMAL, priority: 32)]
    public function onCustomerRegistered(CustomerCreateOrUpdateMinimalEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $customer = $event->getCustomer();

            if (null !== $customer) {
                $this->publisher->publish('customer.created', $customer);
            }
        });
    }

    /**
     * The API saves the model without going through the actions: a customer or an order it
     * creates and a product it writes are published from here. It has no state from before the
     * write to compare with, so a product written through the API is published even
     * when the write changed nothing.
     */
    #[AsEventListener(event: ResourcePersistedEvent::class, priority: 32)]
    public function onResourcePersisted(ResourcePersistedEvent $event): void
    {
        $this->safely(function () use ($event): void {
            if ($event->isDelete()) {
                return;
            }

            if (CustomerResource::class === $event->getResourceClass() && $event->isCreate()) {
                $this->publisher->publish('customer.created', $event->getModel());
            }

            if (OrderResource::class === $event->getResourceClass() && $event->isCreate()) {
                $this->publisher->publish('order.created', $event->getModel());
            }

            if (ProductResource::class === $event->getResourceClass() && !$event->isCreate()) {
                $this->publisher->publish('product.updated', $event->getModel());
            }
        });
    }

    #[AsEventListener(event: TheliaEvents::PRODUCT_UPDATE, priority: 192)]
    public function rememberProductBeforeUpdate(ProductUpdateEvent $event): void
    {
        $this->safely(function () use ($event): void {
            if (!$this->publisher->hasSubscriber('product.updated')) {
                return;
            }

            $this->productBeforeUpdate[$event] = $this->productFingerprint->of((int) $event->getProductId(), $event->getLocale());
        });
    }

    #[AsEventListener(event: TheliaEvents::PRODUCT_UPDATE, priority: 32)]
    public function onProductUpdated(ProductUpdateEvent $event): void
    {
        $this->safely(function () use ($event): void {
            $product = $event->getProduct();

            if (null === $product || !isset($this->productBeforeUpdate[$event])) {
                return;
            }

            if ($this->productBeforeUpdate[$event] === $this->productFingerprint->of((int) $event->getProductId(), $event->getLocale())) {
                return;
            }

            $this->publisher->publish('product.updated', $product);
        });
    }

    private function safely(\Closure $handler): void
    {
        try {
            $handler();
        } catch (\Throwable $exception) {
            Tlog::getInstance()->error(\sprintf('A webhook listener failed: %s', $exception::class));
        }
    }
}
