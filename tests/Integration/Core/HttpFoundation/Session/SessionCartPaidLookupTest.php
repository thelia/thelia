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

namespace Thelia\Tests\Integration\Core\HttpFoundation\Session;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * pipe issue-3980 — V4-propel.
 *
 * Session::getSessionCart() now runs hasBeenPaidFor($cart) — a fresh
 * OrderQuery::create()->filterByCartId()->joinWithOrderStatus()->find() — on every call
 * that hands back an already-valid cart, with nothing memoizing the answer for the
 * lifetime of the request. getSessionCart() is called several times per front request
 * (BaseFrontController, the Cart/Payment/Delivery loops, TaxEngine, BaseFacade …), so a
 * single page render now costs one extra `order` read per call site instead of the one
 * it costed before this change (zero).
 */
final class SessionCartPaidLookupTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    public function testThreeReadsOfTheSameSessionCartCostAtMostOneOrderLookup(): void
    {
        $factory = $this->createFixtureFactory();

        // An anonymous cart — no customer attached — so Session::isValidCart() accepts
        // it unconditionally and getSessionCart() takes the hasBeenPaidFor() branch on
        // every one of the three calls below. The cart carries no order at all: this is
        // the ordinary case of a visitor who has not even reached checkout yet, not an
        // edge case involving a paid or cancelled order.
        $cart = $factory->cart();

        /** @var Session $session */
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setSessionCart($cart);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');

        $statements = $this->recordSqlQueries(static function () use ($session, $dispatcher, $cart): void {
            $first = $session->getSessionCart($dispatcher);
            $second = $session->getSessionCart($dispatcher);
            $third = $session->getSessionCart($dispatcher);

            self::assertSame($cart->getId(), $first->getId());
            self::assertSame($cart->getId(), $second->getId());
            self::assertSame($cart->getId(), $third->getId());
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'order'),
            'Three reads of the same still-unpaid session cart within a single request must cost a single "has it been paid" lookup, not one per call.',
        );
    }
}
