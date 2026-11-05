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

namespace Thelia\Domain\Payment\Service;

use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;

/**
 * The lines written while the journal is locked, held back until the outermost lock is
 * released: the listeners of the announcement call payment modules and move the order,
 * and must not run with the journal of the order still locked.
 *
 * @internal state of {@see PaymentTransactionRecorder}, one per recorder
 */
final class PaymentAnnouncementQueue
{
    private int $depth = 0;

    /** @var list<array{Order, OrderPaymentTransaction, ?string}> */
    private array $lines = [];

    public function enter(): void
    {
        ++$this->depth;
    }

    public function push(Order $order, OrderPaymentTransaction $transaction, ?string $moduleCode): void
    {
        $this->lines[] = [$order, $transaction, $moduleCode];
    }

    /**
     * @return list<array{Order, OrderPaymentTransaction, ?string}> the lines to announce now, once the outermost lock is left
     */
    public function leave(): array
    {
        if (--$this->depth > 0) {
            return [];
        }

        $lines = $this->lines;
        $this->lines = [];

        return $lines;
    }
}
