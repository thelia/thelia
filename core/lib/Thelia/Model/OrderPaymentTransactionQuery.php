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

    /**
     * Whether the journal holds, for one of these movements, a line that happened or may
     * have happened — succeeded or still pending.
     *
     * @param list<PaymentTransactionType> $types
     */
    public function holdsMovement(int $orderId, array $types, ?ConnectionInterface $con = null): bool
    {
        return self::create()
            ->filterByOrderId($orderId)
            ->filterByType(array_map(static fn (PaymentTransactionType $type): string => $type->value, $types), Criteria::IN)
            ->filterByState([PaymentTransactionState::SUCCEEDED->value, PaymentTransactionState::PENDING->value], Criteria::IN)
            ->exists($con);
    }

    /**
     * The line without a provider reference written since $since for this movement, with
     * this outcome and this amount: the same report arriving again.
     */
    public function findRecentWithoutReference(
        int $orderId,
        PaymentTransactionType $type,
        PaymentTransactionState $state,
        string $amount,
        \DateTimeInterface $since,
        ?ConnectionInterface $con = null,
    ): ?OrderPaymentTransaction {
        return self::create()
            ->filterByOrderId($orderId)
            ->filterByTypeEnum($type)
            ->filterByState($state->value)
            ->filterByPspReference(null, Criteria::ISNULL)
            ->filterByAmount($amount)
            ->filterByCreatedAt($since, Criteria::GREATER_EQUAL)
            ->orderById(Criteria::DESC)
            ->findOne($con);
    }

    /**
     * The capture of this amount asked since $since that did not fail: a repeated click
     * or a retried call is answered with it rather than sent to the provider again.
     */
    public function findRecentCaptureOf(int $orderId, string $amount, \DateTimeInterface $since, ?ConnectionInterface $con = null): ?OrderPaymentTransaction
    {
        return self::create()
            ->filterByOrderId($orderId)
            ->filterByTypeEnum(PaymentTransactionType::CAPTURE)
            ->filterByState([PaymentTransactionState::SUCCEEDED->value, PaymentTransactionState::PENDING->value], Criteria::IN)
            ->filterByAmount($amount)
            ->filterByCreatedAt($since, Criteria::GREATER_EQUAL)
            ->orderById(Criteria::DESC)
            ->findOne($con);
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
