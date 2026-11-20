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

namespace Thelia\Tests\Integration\Company;

use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderQuery;
use Thelia\Tests\Support\Order\RecordsStatements;

/**
 * The company an order is placed for, and the buyer who placed it, frozen on the order.
 */
final class OrderCompanyAttachmentTest extends OrderCompanyTestCase
{
    use RecordsStatements;

    public function testAnOrderIsAttachedToTheCompanyOfItsBuyerAndRemembersWhoPlacedIt(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);

        $order = $this->place($this->cartOf($buyer, '100'), $buyer);

        $stored = OrderQuery::create()->findPk($order->getId());
        self::assertSame((int) $company->getId(), $stored?->getCompanyId());
        self::assertSame((int) $buyer->getId(), $stored->getPlacedByCustomerId());
        self::assertSame((int) $buyer->getId(), (int) $stored->getCustomerId(), 'The owner of the order is still the account.');
    }

    public function testAnOrderPlacedOutsideTheCheckoutIsAttachedAsWell(): void
    {
        // The installments of a subscription place their order this way: no buyer at the keyboard.
        $company = $this->company();
        $buyer = $this->buyerOf($company);

        $order = $this->place($this->cartOf($buyer, '100'), $buyer, atTheKeyboard: false);

        $stored = OrderQuery::create()->findPk($order->getId());
        self::assertSame((int) $company->getId(), $stored?->getCompanyId());
        self::assertSame((int) $buyer->getId(), $stored->getPlacedByCustomerId());
    }

    public function testAnOrderCreatedByAnAdministratorForABuyerIsAttachedToTheirCompany(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $cart = $this->cartOf($buyer, '100');

        $event = new OrderManualEvent($this->sessionOrder(), $cart->getCurrency(), $this->lang(), $cart, $buyer);
        $event->setUseOrderDefinedAddresses(true);
        $this->dispatcher->dispatch($event, TheliaEvents::ORDER_CREATE_MANUAL);

        $stored = OrderQuery::create()->findPk($event->getPlacedOrder()->getId());
        self::assertSame((int) $company->getId(), $stored?->getCompanyId());
        self::assertSame((int) $buyer->getId(), $stored->getPlacedByCustomerId());
    }

    public function testABuyerWhoChangesCompanyLeavesTheirOrdersWhereTheyWere(): void
    {
        $first = $this->company('First');
        $second = $this->company('Second');
        $buyer = $this->buyerOf($first);
        $before = $this->place($this->cartOf($buyer, '100'), $buyer);

        $this->facade->detach($buyer);
        $this->facade->attach($second, $buyer, 'buyer', null, null, null);
        $this->getService(\Thelia\Domain\Company\Service\CompanyMembershipReader::class)->forget((int) $buyer->getId());
        $after = $this->place($this->cartOf($buyer, '100'), $buyer);

        $stored = OrderQuery::create()->findPk($before->getId());
        self::assertSame((int) $first->getId(), $stored?->getCompanyId(), 'The order stays with the company it was placed for.');
        self::assertSame((int) $buyer->getId(), $stored->getPlacedByCustomerId());
        self::assertSame((int) $second->getId(), OrderQuery::create()->findPk($after->getId())?->getCompanyId());
    }

    public function testACustomerWithoutACompanyGetsNeitherColumn(): void
    {
        $customer = $this->customer();

        $order = $this->place($this->cartOf($customer, '100'), $customer);

        $stored = OrderQuery::create()->findPk($order->getId());
        self::assertNull($stored?->getCompanyId());
        self::assertNull($stored->getPlacedByCustomerId());
    }

    public function testAnOrderOfACustomerWithoutACompanyReadsNothingOfTheCompanyTables(): void
    {
        $customer = $this->customer();
        $cart = $this->cartOf($customer, '100');
        $this->getService(\Thelia\Domain\Company\Service\CompanyMembershipReader::class)->reset();

        $statements = $this->statementsDuring(fn () => $this->place($cart, $customer));

        $touchingACompanyTable = array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/\b(FROM|JOIN|INTO|UPDATE)\s+`company/i', $statement),
        ));

        self::assertCount(1, $touchingACompanyTable, 'Only the membership lookup, which finds nothing.');
        self::assertMatchesRegularExpression('/^SELECT .* FROM `company_customer`/s', $touchingACompanyTable[0]);
    }

    public function testTheMembershipOfABuyerIsReadOnceForAnOrder(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company, orderLimit: '1000.000000');
        $cart = $this->cartOf($buyer, '100');
        $this->getService(\Thelia\Domain\Company\Service\CompanyMembershipReader::class)->reset();

        $statements = $this->statementsDuring(fn () => $this->place($cart, $buyer));

        $memberships = array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/^SELECT .* FROM `company_customer`\s+INNER JOIN/s', $statement));
        self::assertLessThanOrEqual(2, \count($memberships), 'Once before the lock and once after it, never once per consumer.');
    }
}
