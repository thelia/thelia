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

namespace Thelia\Domain\Payment;

use Thelia\Domain\Payment\DTO\InstantShare;
use Thelia\Domain\Payment\DTO\PaymentComposition;
use Thelia\Domain\Payment\DTO\PlannedPaymentPart;
use Thelia\Domain\Payment\Exception\PaymentCompositionRefusedException;

/**
 * Splits an order total between the instant shares and the payment module that charges
 * the rest. Pure: values in, composition out.
 *
 * Amounts are decimal strings, computed as integers of a millionth (no float, and no
 * bcmath, which the core does not require). Every amount is rounded to the cent, the
 * smallest sum a provider can charge, so the parts always add up to the total.
 */
final readonly class OrderPaymentSplitter
{
    private const SCALE = 6;
    private const CENT = 10_000;

    /**
     * @param list<InstantShare> $instantShares in settlement order
     *
     * @throws PaymentCompositionRefusedException
     */
    public function split(
        string $orderTotal,
        array $instantShares,
        ?int $providerModuleId,
        bool $providerChargesTheBalance,
    ): PaymentComposition {
        $remaining = $this->toRoundedAmount($orderTotal);
        $instantParts = [];

        foreach ($instantShares as $share) {
            // A share can never exceed what is still due, and a spent-out order takes no more.
            $amount = min($this->toRoundedAmount($share->amountApplied), $remaining);

            if ($amount <= 0) {
                continue;
            }

            $instantParts[] = [$share->moduleId, $amount];
            $remaining -= $amount;
        }

        if ([] === $instantParts) {
            return new PaymentComposition([], $this->format($this->toRoundedAmount($orderTotal)));
        }

        $parts = [];
        $lastInstant = array_key_last($instantParts);

        foreach ($instantParts as $position => [$moduleId, $amount]) {
            $parts[] = new PlannedPaymentPart(
                $moduleId,
                $this->format($amount),
                $remaining <= 0 && $position === $lastInstant,
                $position,
                true,
            );
        }

        if ($remaining <= 0) {
            return new PaymentComposition($parts, $this->format(0));
        }

        if (null === $providerModuleId) {
            throw PaymentCompositionRefusedException::remainderWithoutProvider();
        }

        if (!$providerChargesTheBalance) {
            throw PaymentCompositionRefusedException::providerCannotChargeBalance();
        }

        $parts[] = new PlannedPaymentPart($providerModuleId, $this->format($remaining), true, \count($parts), false);

        return new PaymentComposition($parts, $this->format($remaining));
    }

    /**
     * Millionths, rounded half up to the cent once, on the digits as given: the third
     * decimal alone says whether a cent is added, so 0.0049995 is nothing. A negative amount
     * counts as nothing.
     */
    private function toRoundedAmount(string $amount): int
    {
        if (1 !== preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', trim($amount), $matches)) {
            throw new \InvalidArgumentException('An amount must be a decimal string.');
        }

        if ('-' === $matches[1]) {
            return 0;
        }

        $fraction = str_pad($matches[3] ?? '', 3, '0');
        $cents = (int) $matches[2] * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return $cents * self::CENT;
    }

    private function format(int $millionths): string
    {
        return \sprintf('%d.%06d', intdiv($millionths, 10 ** self::SCALE), $millionths % 10 ** self::SCALE);
    }
}
