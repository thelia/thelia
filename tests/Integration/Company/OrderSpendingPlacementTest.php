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

use Thelia\Domain\Company\Checkout\Exception\CompanyOrderLimitExceededException;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderNotPermittedException;
use Thelia\Domain\Company\Checkout\Exception\CompanyPeriodLimitExceededException;
use Thelia\Domain\Company\Checkout\Exception\CompanySuspendedException;
use Thelia\Domain\Company\Checkout\Exception\CompanyWithoutBillingAddressException;
use Thelia\Domain\Company\Checkout\Service\CompanyPlacementGuard;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\Customer;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Tests\Support\Order\RecordsStatements;

/**
 * The order is judged again where it is written, once it is whole and the buyer is locked: what
 * the tunnel read a moment before may have been used up by another cart of the same buyer.
 */
final class OrderSpendingPlacementTest extends OrderCompanyTestCase
{
    use RecordsStatements;

    public function testAnOrderOverTheLimitOfAnOrderIsRefusedWhereItIsWritten(): void
    {
        [$buyer] = $this->limitedBuyer();

        $this->expectException(CompanyOrderLimitExceededException::class);

        $this->place($this->cartOf($buyer, '600'), $buyer);
    }

    public function testAnOrderThatMakesTheMonthGoOverItsLimitIsRefusedWhereItIsWritten(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->orderInTheBooks($buyer, $company, '1800');

        $this->expectException(CompanyPeriodLimitExceededException::class);

        $this->place($this->cartOf($buyer, '300'), $buyer);
    }

    public function testAnOrderWithinBothLimitsIsPlacedAndNotCountedAgainstItself(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->orderInTheBooks($buyer, $company, '1800');

        // 1 800 + 150 = 1 950: counting the order being written twice would make it 2 100.
        $order = $this->place($this->cartOf($buyer, '150'), $buyer);

        self::assertSame((int) $company->getId(), OrderQuery::create()->findPk($order->getId())?->getCompanyId());
    }

    public function testAnOrderThatReachesTheLimitOfThePeriodExactlyIsPlaced(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->orderInTheBooks($buyer, $company, '1800');

        $order = $this->place($this->cartOf($buyer, '200'), $buyer);

        self::assertNotNull($order->getId());
    }

    public function testACancelledOrderOfThePeriodIsNotCountedWhereTheOrderIsWritten(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->orderInTheBooks($buyer, $company, '1600');
        $this->moveTo($this->orderInTheBooks($buyer, $company, '400'), OrderStatus::CODE_CANCELED);

        $order = $this->place($this->cartOf($buyer, '300'), $buyer);

        self::assertNotNull($order->getId());
    }

    public function testTheOrderOfABuyerWithoutLimitsIsPlaced(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);

        self::assertNotNull($this->place($this->cartOf($buyer, '9000'), $buyer)->getId());
    }

    public function testAnOrderWrittenByAnAdministratorOrBySubscriptionIsNotBoundByTheLimits(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->orderInTheBooks($buyer, $company, '1800');

        $order = $this->place($this->cartOf($buyer, '600'), $buyer, atTheKeyboard: false);

        self::assertSame((int) $company->getId(), OrderQuery::create()->findPk($order->getId())?->getCompanyId(), 'It is still attached to the company, and counts for the next order of the buyer.');
    }

    public function testASuspendedCompanyPlacesNothingAndItsHistoryStaysReadable(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $before = $this->orderInTheBooks($buyer, $company, '100');
        $this->facade->suspend($company);
        $this->getService(CompanyMembershipReader::class)->reset();

        try {
            $this->place($this->cartOf($buyer, '10'), $buyer);
            self::fail('A suspended company placed an order.');
        } catch (CompanySuspendedException $refusal) {
            self::assertStringContainsString('suspended', $refusal->getMessage());
        }

        $this->reader->reset();
        self::assertTrue($this->checker->canAccessOrder($buyer, $before), 'The buyer still reads what the company ordered.');
    }

    public function testACompanyWithoutBillingAddressPlacesNothingWhereTheOrderIsWrittenButStillGetsTheOrdersAnAdministratorPlacesForIt(): void
    {
        $company = $this->company(withBillingAddress: false);
        $buyer = $this->buyerOf($company);

        try {
            $this->place($this->cartOf($buyer, '10'), $buyer);
            self::fail('A company without billing address placed an order.');
        } catch (CompanyWithoutBillingAddressException $refusal) {
            self::assertStringContainsString('no billing address', $refusal->getMessage());
        }

        $order = $this->place($this->cartOf($buyer, '10'), $buyer, atTheKeyboard: false);

        self::assertSame((int) $company->getId(), OrderQuery::create()->findPk($order->getId())?->getCompanyId());
    }

    public function testASuspendedCompanyStillGetsTheOrdersAnAdministratorPlacesForIt(): void
    {
        [$buyer] = $this->limitedBuyer();
        $company = $this->company('Suspended');
        $member = $this->buyerOf($company);
        $this->facade->suspend($company);

        $order = $this->place($this->cartOf($member, '10'), $member, atTheKeyboard: false);

        self::assertSame((int) $company->getId(), OrderQuery::create()->findPk($order->getId())?->getCompanyId());
        self::assertNotSame($buyer->getId(), $member->getId());
    }

    public function testARoleWithoutThePermissionToOrderPlacesNothing(): void
    {
        $company = $this->company();
        $approver = $this->buyerOf($company, 'approver');

        $this->expectException(CompanyOrderNotPermittedException::class);

        $this->place($this->cartOf($approver, '10'), $approver);
    }

    public function testACustomerWithoutACompanyIsNotJudged(): void
    {
        $customer = $this->customer();

        self::assertNotNull($this->place($this->cartOf($customer, '99999'), $customer)->getId());
    }

    public function testTheRowOfTheBuyerIsLockedBeforeTheTransactionReadsAnythingItDoesNotLock(): void
    {
        [$buyer] = $this->limitedBuyer();
        $cart = $this->cartOf($buyer, '100');

        $statements = $this->statementsDuring(fn () => $this->place($cart, $buyer));

        $lockOfTheBuyer = $this->firstStatementMatching($statements, '/FROM `company_customer` WHERE `id` = .* FOR UPDATE/s');
        $lockOfTheCart = $this->firstStatementMatching($statements, '/FROM `cart` WHERE `id` = .* FOR UPDATE/s');

        self::assertNotNull($lockOfTheBuyer, 'The row of the buyer is locked by its primary key.');
        self::assertNotNull($lockOfTheCart);
        self::assertLessThan($lockOfTheCart, $lockOfTheBuyer, 'The first lock of the placement is the buyer, ahead of the cart and of every plain read.');
    }

    public function testAnOrderIsJudgedOnTheLimitsOfTheBuyerAsTheyAreOnceTheRowIsLocked(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $order = $this->orderInTheBooks($buyer, $company, '300');
        $guard = $this->getService(CompanyPlacementGuard::class);
        $this->getService(CompanyMembershipReader::class)->reset();
        $hold = $guard->prepare($buyer);
        self::assertNotNull($hold);

        // Another request lowers the limit of the order after the buyer was read, before their row is locked.
        $row = CompanyCustomerQuery::create()->findPk($hold->companyCustomerId);
        $row?->setOrderLimit('100')->save($this->getPropelConnection());
        $guard->lock($hold, $this->getPropelConnection());

        $this->expectException(CompanyOrderLimitExceededException::class);

        $guard->verify($hold, $order, $this->getPropelConnection());
    }

    /**
     * @param list<string> $statements
     */
    private function firstStatementMatching(array $statements, string $pattern): ?int
    {
        foreach ($statements as $index => $statement) {
            if (1 === preg_match($pattern, $statement)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{0: Customer, 1: Company}
     */
    private function limitedBuyer(): array
    {
        $company = $this->company();

        return [$this->buyerOf($company, orderLimit: '500.000000', periodLimit: '2000.000000', period: 'month'), $company];
    }
}
