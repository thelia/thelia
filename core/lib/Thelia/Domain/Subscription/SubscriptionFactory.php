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

namespace Thelia\Domain\Subscription;

use Propel\Runtime\Propel;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Subscription\SubscriptionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\CMS\Publication\PublicationMoment;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\PeriodUnit;
use Thelia\Domain\Subscription\Enum\SubscriptionOrderState;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Map\SubscriptionTableMap;
use Thelia\Model\Order;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionOrder;
use Thelia\Model\SubscriptionPlan;

/**
 * Creates the subscription a customer took with an order.
 *
 * The values of the plan are copied onto it, the unit price included: editing or deleting
 * the plan later changes nothing for a subscription already running.
 *
 * Without a trial, the order the customer paid is the first installment: it is recorded as
 * paid, and the next one is due at the end of the first period. With a trial, the customer
 * paid nothing yet: the first installment is charged when the trial ends, and the periods
 * are anchored on that day.
 */
final readonly class SubscriptionFactory
{
    public function __construct(
        private SubscriptionPlanPricing $pricing,
        private BillingCalendar $calendar,
        private SubscriptionStateMachine $stateMachine,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
    ) {
    }

    public function fromInitialOrder(
        Order $order,
        SubscriptionPlan $plan,
        ProductSaleElements $productSaleElements,
        int $quantity,
        ?CustomerPaymentMethod $paymentMethod,
    ): Subscription {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('A subscription delivers one unit at least.');
        }

        $now = PublicationMoment::atTheSecond($this->clock->now());
        $unit = PeriodUnit::from((string) $plan->getPeriodUnit());
        $periodCount = max(1, (int) $plan->getPeriodCount());
        $trialDays = max(0, (int) $plan->getTrialDays());
        $hasTrial = $trialDays > 0;

        if ($hasTrial) {
            $periodEnd = $this->calendar->trialEnd($now, $trialDays);
            $anchorDay = $this->calendar->anchorDayOf($periodEnd);
        } else {
            $anchorDay = $this->calendar->anchorDayOf($now);
            $periodEnd = $this->calendar->periodEnd($now, $unit, $periodCount, $anchorDay);
        }

        $subscription = (new Subscription())
            ->setRef(self::newRef())
            ->setCustomerId($order->getCustomerId())
            ->setSubscriptionPlanId($plan->getId())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity($quantity)
            ->setCurrencyId($order->getCurrencyId())
            ->setLangId($order->getLangId())
            ->setPeriodUnit($unit->value)
            ->setPeriodCount($periodCount)
            ->setInstallmentCount($plan->getInstallmentCount())
            ->setCommitmentCount($plan->getCommitmentCount())
            ->setPausable((int) $plan->getPausable())
            ->setUnitPrice($this->pricing->priceOf($plan, $order->getCurrency()))
            ->setInitialOrderId($order->getId())
            ->setDeliveryModuleId($order->getDeliveryModuleId())
            ->setPaymentModuleId($order->getPaymentModuleId())
            ->setCustomerPaymentMethodId($paymentMethod?->getId())
            ->setStatus(($hasTrial ? SubscriptionStatus::Trial : SubscriptionStatus::Active)->value)
            ->setBillingAnchorDay($anchorDay)
            ->setCurrentPeriodStart(\DateTime::createFromImmutable($now))
            ->setCurrentPeriodEnd(\DateTime::createFromImmutable($periodEnd))
            ->setNextBillingAt(\DateTime::createFromImmutable($periodEnd))
            ->setFailureCount(0)
            ->setInstallmentsBilled($hasTrial ? 0 : 1);

        $connection = Propel::getConnection(SubscriptionTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $subscription->save($connection);

            if (!$hasTrial) {
                (new SubscriptionOrder())
                    ->setSubscriptionId($subscription->getId())
                    ->setInstallmentNumber(1)
                    ->setOrderId($order->getId())
                    ->setState(SubscriptionOrderState::Paid->value)
                    ->setAttemptCount(1)
                    ->save($connection);
            }

            $this->stateMachine->recordCreation($subscription, ActorType::Customer, (int) $order->getCustomerId(), \sprintf('Taken with order %s', (string) $order->getRef()), $connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $this->dispatcher->dispatch(new SubscriptionEvent($subscription), TheliaEvents::SUBSCRIPTION_CREATE);

        return $subscription;
    }

    private static function newRef(): string
    {
        return 'SUB'.strtoupper(bin2hex(random_bytes(6)));
    }
}
