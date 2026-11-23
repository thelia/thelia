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

namespace Thelia\Tests\Unit\Domain\Company\Checkout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderLimitExceededException;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderNotPermittedException;
use Thelia\Domain\Company\Checkout\Exception\CompanyPeriodLimitExceededException;
use Thelia\Domain\Company\Checkout\Exception\CompanySuspendedException;
use Thelia\Domain\Company\Checkout\Exception\CompanyWithoutBillingAddressException;
use Thelia\Domain\Company\Checkout\Service\CompanySpendingJudge;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\DTO\CompanyMembership;

/**
 * The rule the checkout and the placement share: what a buyer's company lets them order.
 *
 * Plafond of 500 per order and 2 000 per month, 1 800 already ordered this month.
 */
final class CompanySpendingJudgeTest extends TestCase
{
    private CompanySpendingJudge $judge;

    protected function setUp(): void
    {
        // The refusals are sentences a buyer reads, so they go through the translator.
        new Translator(new RequestStack());
        $this->judge = new CompanySpendingJudge();
    }

    public function testAnOrderOverTheLimitOfAnOrderIsRefused(): void
    {
        // The month has room (100 + 600 <= 2 000): only the limit of one order is over.
        $refusals = $this->judge->refusalsFor($this->membership(orderLimit: '500.000000', periodLimit: '2000.000000', period: 'month'), '600.000000', $this->spent('100.000000'));

        self::assertCount(1, $refusals);
        self::assertInstanceOf(CompanyOrderLimitExceededException::class, $refusals[0]);
        self::assertSame('company-order-limit-exceeded', $refusals[0]->violationCode());
        self::assertSame(['limit' => '500', 'total' => '600'], $refusals[0]->violationDetails());
        self::assertStringContainsString('600', $refusals[0]->getMessage());
        self::assertStringContainsString('500', $refusals[0]->getMessage());
    }

    public function testAnOrderThatMakesThePeriodGoOverItsLimitIsRefused(): void
    {
        // 1 800 + 300 = 2 100 > 2 000, although 300 is within the 500 of one order.
        $refusals = $this->judge->refusalsFor($this->membership(orderLimit: '500.000000', periodLimit: '2000.000000', period: 'month'), '300.000000', $this->spent('1800.000000'));

        self::assertCount(1, $refusals);
        self::assertInstanceOf(CompanyPeriodLimitExceededException::class, $refusals[0]);
        self::assertSame('company-period-limit-exceeded', $refusals[0]->violationCode());
        self::assertSame(['limit' => '2000', 'period' => 'month', 'spent' => '1800', 'total' => '300'], $refusals[0]->violationDetails());
    }

    public function testAnOrderThatFitsBothLimitsIsAccepted(): void
    {
        // 1 800 + 150 = 1 950 <= 2 000 and 150 <= 500.
        self::assertSame([], $this->judge->refusalsFor($this->membership(orderLimit: '500.000000', periodLimit: '2000.000000', period: 'month'), '150.000000', $this->spent('1800.000000')));
    }

    public function testAnOrderThatReachesALimitExactlyIsAccepted(): void
    {
        self::assertSame([], $this->judge->refusalsFor($this->membership(orderLimit: '500.000000', periodLimit: '2000.000000', period: 'month'), '200.000000', $this->spent('1800.000000')));
        self::assertSame([], $this->judge->refusalsFor($this->membership(orderLimit: '500.000000'), '500.000000', $this->spent('0')));
    }

    public function testABuyerWithoutLimitsOrdersAnyAmount(): void
    {
        self::assertSame([], $this->judge->refusalsFor($this->membership(), '99999.000000', $this->spent('1800.000000')));
    }

    public function testBothLimitsAreReportedWhenBothAreOver(): void
    {
        // 1 800 + 600 = 2 400 > 2 000 and 600 > 500: the buyer is told about both, the order limit first.
        $refusals = $this->judge->refusalsFor($this->membership(orderLimit: '500.000000', periodLimit: '2000.000000', period: 'month'), '600.000000', $this->spent('1800.000000'));

        self::assertCount(2, $refusals);
        self::assertInstanceOf(CompanyOrderLimitExceededException::class, $refusals[0]);
        self::assertInstanceOf(CompanyPeriodLimitExceededException::class, $refusals[1]);
    }

    public function testASuspendedCompanyPlacesNothingWhateverTheAmount(): void
    {
        $refusals = $this->judge->refusalsFor($this->membership(active: false), '1.000000', $this->spent('0'));

        self::assertCount(1, $refusals);
        self::assertInstanceOf(CompanySuspendedException::class, $refusals[0]);
        self::assertSame('company-suspended', $refusals[0]->violationCode());
    }

    public function testACompanyWithoutBillingAddressPlacesNothingEvenForARoleThatMayOrder(): void
    {
        $refusals = $this->judge->refusalsFor($this->membership(billingAddress: false), '1.000000', $this->spent('0'));

        self::assertCount(1, $refusals);
        self::assertInstanceOf(CompanyWithoutBillingAddressException::class, $refusals[0]);
        self::assertSame('company-without-billing-address', $refusals[0]->violationCode());
    }

    public function testACompanyWithoutBillingAddressIsToldWhateverTheLimitsAndBeforeAMissingPermission(): void
    {
        $refusals = $this->judge->refusalsFor($this->membership(orderLimit: '1.000000', permissions: [], billingAddress: false), '600.000000', $this->spent('0'));

        self::assertCount(1, $refusals);
        self::assertInstanceOf(CompanyWithoutBillingAddressException::class, $refusals[0]);
        self::assertInstanceOf(CompanyWithoutBillingAddressException::class, $this->judge->refusalsFor($this->membership(billingAddress: false), null, $this->spent('0'))[0]);
    }

    public function testASuspendedCompanyIsToldBeforeAMissingBillingAddress(): void
    {
        $refusals = $this->judge->refusalsFor($this->membership(active: false, billingAddress: false), '1.000000', $this->spent('0'));

        self::assertInstanceOf(CompanySuspendedException::class, $refusals[0]);
    }

    public function testACompanyWithABillingAddressIsNotRefusedForIt(): void
    {
        self::assertSame([], $this->judge->refusalsFor($this->membership(billingAddress: true), '1.000000', $this->spent('0')));
    }

    public function testARoleWithoutThePermissionToOrderPlacesNothing(): void
    {
        $refusals = $this->judge->refusalsFor($this->membership(permissions: [CompanyPermission::ORDER_VIEW_OWN]), '1.000000', $this->spent('0'));

        self::assertCount(1, $refusals);
        self::assertInstanceOf(CompanyOrderNotPermittedException::class, $refusals[0]);
        self::assertSame('company-order-not-permitted', $refusals[0]->violationCode());
    }

    public function testASuspendedCompanyIsToldBeforeAMissingPermission(): void
    {
        $refusals = $this->judge->refusalsFor($this->membership(active: false, permissions: []), '1.000000', $this->spent('0'));

        self::assertInstanceOf(CompanySuspendedException::class, $refusals[0]);
    }

    public function testACartNotPricedYetIsJudgedOnWhatDoesNotDependOnAnAmount(): void
    {
        self::assertSame([], $this->judge->refusalsFor($this->membership(orderLimit: '1.000000'), null, $this->spent('0')));
        self::assertInstanceOf(CompanySuspendedException::class, $this->judge->refusalsFor($this->membership(active: false), null, $this->spent('0'))[0]);
    }

    public function testWhatWasSpentIsOnlyReadWhenALimitOverAPeriodNeedsIt(): void
    {
        $reads = 0;
        $counting = static function () use (&$reads): string {
            ++$reads;

            return '0.000000';
        };

        $this->judge->refusalsFor($this->membership(orderLimit: '500.000000'), '100.000000', $counting);
        $this->judge->refusalsFor($this->membership(), '100.000000', $counting);
        self::assertSame(0, $reads, 'No limit over a period: no statement.');

        $this->judge->refusalsFor($this->membership(periodLimit: '2000.000000', period: 'week'), '100.000000', $counting);
        self::assertSame(1, $reads);
    }

    /**
     * @param list<string>|null $permissions
     */
    private function membership(bool $active = true, ?string $orderLimit = null, ?string $periodLimit = null, ?string $period = null, ?array $permissions = null, bool $billingAddress = true): CompanyMembership
    {
        return new CompanyMembership(
            companyId: 3,
            customerId: 12,
            roleCode: 'buyer',
            permissions: $permissions ?? [CompanyPermission::ORDER_PLACE, CompanyPermission::ORDER_VIEW_OWN],
            companyActive: $active,
            companyHasBillingAddress: $billingAddress,
            orderLimit: $orderLimit,
            periodLimit: $periodLimit,
            period: $period,
        );
    }

    /**
     * @return callable(): string
     */
    private function spent(string $amount): callable
    {
        return static fn (): string => $amount;
    }
}
