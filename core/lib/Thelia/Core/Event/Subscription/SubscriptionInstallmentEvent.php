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

namespace Thelia\Core\Event\Subscription;

use Thelia\Domain\Subscription\RecurringChargeResult;
use Thelia\Model\Order;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionOrder;

/**
 * Tells how the charge of an installment went (TheliaEvents::SUBSCRIPTION_INSTALLMENT_PAID
 * or SUBSCRIPTION_INSTALLMENT_FAILED), once the subscription is written. The order is
 * missing when the installment failed before it could be placed; the result then says why.
 */
final class SubscriptionInstallmentEvent extends SubscriptionEvent
{
    public function __construct(
        Subscription $subscription,
        private SubscriptionOrder $installment,
        private RecurringChargeResult $result,
        private ?Order $order = null,
    ) {
        parent::__construct($subscription);
    }

    public function getInstallment(): SubscriptionOrder
    {
        return $this->installment;
    }

    public function setInstallment(SubscriptionOrder $installment): self
    {
        $this->installment = $installment;

        return $this;
    }

    public function getResult(): RecurringChargeResult
    {
        return $this->result;
    }

    public function setResult(RecurringChargeResult $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }
}
