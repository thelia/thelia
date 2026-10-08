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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Model\Base\OrderPaymentTransactionQuery as BaseOrderPaymentTransactionQuery;

class OrderPaymentTransactionQuery extends BaseOrderPaymentTransactionQuery
{
    public function filterSucceeded(): static
    {
        return $this->filterByState(PaymentTransactionState::SUCCEEDED->value);
    }

    public function filterByTypeEnum(PaymentTransactionType $type): static
    {
        return $this->filterByType($type->value);
    }

    /**
     * The line a provider notification for this movement already wrote, if any.
     */
    public function findByReference(int $orderId, PaymentTransactionType $type, string $pspReference, ?ConnectionInterface $con = null): ?OrderPaymentTransaction
    {
        return self::create()
            ->filterByOrderId($orderId)
            ->filterByTypeEnum($type)
            ->filterByPspReference($pspReference)
            ->findOne($con);
    }

    public function findLatestSucceededAuthorization(int $orderId, ?ConnectionInterface $con = null): ?OrderPaymentTransaction
    {
        return self::create()
            ->filterByOrderId($orderId)
            ->filterByTypeEnum(PaymentTransactionType::AUTHORIZATION)
            ->filterSucceeded()
            ->orderById(Criteria::DESC)
            ->findOne($con);
    }

    public function hasSucceededCapture(int $orderId, ?ConnectionInterface $con = null): bool
    {
        return self::create()
            ->filterByOrderId($orderId)
            ->filterByTypeEnum(PaymentTransactionType::CAPTURE)
            ->filterSucceeded()
            ->exists($con);
    }

    /**
     * The journal of an order, latest movement first.
     *
     * @return \Propel\Runtime\Collection\ObjectCollection<OrderPaymentTransaction>
     */
    public function findJournal(int $orderId, ?ConnectionInterface $con = null): \Propel\Runtime\Collection\ObjectCollection
    {
        return self::create()
            ->filterByOrderId($orderId)
            ->orderById(Criteria::DESC)
            ->find($con);
    }
}
