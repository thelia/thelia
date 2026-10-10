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

use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Model\Base\OrderPaymentTransaction as BaseOrderPaymentTransaction;

class OrderPaymentTransaction extends BaseOrderPaymentTransaction
{
    public function getTypeEnum(): ?PaymentTransactionType
    {
        return PaymentTransactionType::tryFrom((string) $this->getType());
    }

    public function getStateEnum(): ?PaymentTransactionState
    {
        return PaymentTransactionState::tryFrom((string) $this->getState());
    }

    public function getActorTypeEnum(): ?OrderHistoryActorType
    {
        return OrderHistoryActorType::tryFrom((string) $this->getActorType());
    }

    public function isPending(): bool
    {
        return PaymentTransactionState::PENDING->value === $this->getState();
    }

    public function isSucceeded(): bool
    {
        return PaymentTransactionState::SUCCEEDED->value === $this->getState();
    }

    public function isFailed(): bool
    {
        return PaymentTransactionState::FAILED->value === $this->getState();
    }

    public function isOfType(PaymentTransactionType $type): bool
    {
        return $type->value === $this->getType();
    }

    public function getAmountAsFloat(): float
    {
        return (float) $this->getAmount();
    }
}
