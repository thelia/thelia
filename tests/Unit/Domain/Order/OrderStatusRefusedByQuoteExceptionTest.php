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

namespace Thelia\Tests\Unit\Domain\Order;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Order\Exception\OrderStatusRefusedByQuoteException;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;

/**
 * The refusal of an order that cannot come back to life is a refused transition like any other, in
 * words the catalogs of the core translate.
 */
final class OrderStatusRefusedByQuoteExceptionTest extends TestCase
{
    public function testItIsAnsweredLikeARefusedTransition(): void
    {
        $refusal = new OrderStatusRefusedByQuoteException('ORD000001', 'canceled', 'not_paid');

        self::assertInstanceOf(OrderStatusTransitionRefusedException::class, $refusal);
        self::assertSame(OrderStatusTransitionRefusedException::TRANSITION_REFUSED, $refusal->getCode());
        self::assertSame(OrderStatusRefusedByQuoteException::MESSAGE, $refusal->getMessage());
        self::assertSame(['ORD000001', 'canceled', 'not_paid'], [$refusal->getOrderRef(), $refusal->getFromStatusCode(), $refusal->getToStatusCode()]);
    }

    public function testTheTransitionTheGraphRefusesKeepsItsOwnMessage(): void
    {
        self::assertSame(
            'Order ORD000001 cannot go from status "paid" to status "new": this transition is not allowed.',
            (new OrderStatusTransitionRefusedException('ORD000001', 'paid', 'new'))->getMessage(),
        );
    }

    public function testTheMessageIsInTheCatalogsOfTheCore(): void
    {
        foreach (['en_US', 'fr_FR', 'de_DE'] as $locale) {
            /** @var array<string, string> $catalog */
            $catalog = require \dirname(__DIR__, 4).'/core/lib/Thelia/Config/I18n/'.$locale.'.php';

            self::assertArrayHasKey(OrderStatusRefusedByQuoteException::MESSAGE, $catalog, $locale);
            self::assertNotSame('', $catalog[OrderStatusRefusedByQuoteException::MESSAGE]);
        }
    }
}
