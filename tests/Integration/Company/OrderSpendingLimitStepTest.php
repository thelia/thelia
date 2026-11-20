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

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\ExpressCheckoutTotal;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepViolationCollectorInterface;
use Thelia\Domain\Company\Checkout\CompanySpendingStepProvider;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderLimitExceededException;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderNotPermittedException;
use Thelia\Domain\Company\Checkout\Exception\CompanyPeriodLimitExceededException;
use Thelia\Domain\Company\Checkout\Exception\CompanySuspendedException;
use Thelia\Domain\Company\Checkout\Service\CompanySpendingJudge;
use Thelia\Domain\Company\Checkout\Service\CompanySpendingReader;
use Thelia\Model\Cart;
use Thelia\Model\Company;
use Thelia\Model\Customer;
use Thelia\Model\OrderStatus;

/**
 * What a company lets a buyer order, asked of a cart at the step of the tunnel and of the API.
 *
 * Order limit 500, limit over a month 2 000, 1 800 already ordered this month and not cancelled:
 * a cart of 600 is refused, one of 300 is refused, one of 150 is accepted.
 */
final class OrderSpendingLimitStepTest extends OrderCompanyTestCase
{
    public function testACartOverTheLimitOfAnOrderIsRefused(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->alreadyOrdered($buyer, $company, '1800');

        $refusal = $this->refusalOf($this->cartOf($buyer, '600'));

        self::assertInstanceOf(CompanyOrderLimitExceededException::class, $refusal);
        self::assertStringContainsString('500', $refusal->getMessage());
        self::assertStringContainsString('600', $refusal->getMessage());
    }

    public function testACartThatMakesTheMonthGoOverItsLimitIsRefused(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->alreadyOrdered($buyer, $company, '1800');

        $refusal = $this->refusalOf($this->cartOf($buyer, '300'));

        self::assertInstanceOf(CompanyPeriodLimitExceededException::class, $refusal);
        self::assertSame(['limit' => '2000', 'period' => 'month', 'spent' => '1800', 'total' => '300'], $refusal->violationDetails());
    }

    public function testACartWithinBothLimitsIsAccepted(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->alreadyOrdered($buyer, $company, '1800');

        self::assertNull($this->refusalOf($this->cartOf($buyer, '150')));
    }

    public function testABuyerWithoutLimitsOrdersAnyAmount(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $this->alreadyOrdered($buyer, $company, '1800');

        self::assertNull($this->refusalOf($this->cartOf($buyer, '5000')));
    }

    public function testAnOrderCancelledOrRefundedThisMonthIsNotCounted(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->alreadyOrdered($buyer, $company, '1600');
        $this->moveTo($this->alreadyOrdered($buyer, $company, '400'), OrderStatus::CODE_CANCELED);
        $this->moveTo($this->alreadyOrdered($buyer, $company, '400'), OrderStatus::CODE_REFUNDED);

        // 1 600 + 300 = 1 900 within 2 000; counting the cancelled and the refunded ones it would be 2 700.
        self::assertNull($this->refusalOf($this->cartOf($buyer, '300')));
        // And an order that is only waiting for its payment is counted: 1 600 + 450 = 2 050.
        self::assertInstanceOf(CompanyPeriodLimitExceededException::class, $this->refusalOf($this->cartOf($buyer, '450')));
    }

    public function testThePostageIsPartOfTheAmountJudged(): void
    {
        [$buyer] = $this->limitedBuyer();

        // 450 of goods and 100 of postage: 550, over the 500 of one order.
        self::assertInstanceOf(CompanyOrderLimitExceededException::class, $this->refusalOf($this->cartOf($buyer, '450', postage: '100')));
        self::assertNull($this->refusalOf($this->cartOf($buyer, '400', postage: '100')));
    }

    public function testTheAmountIsJudgedInTheDefaultCurrency(): void
    {
        [$buyer] = $this->limitedBuyer();
        $twoPerEuro = $this->fixtures->currency(['code' => 'XTS', 'rate' => 2.0]);

        // 1 000 in a currency worth half an euro is 500: the limit of an order, reached and not passed.
        self::assertNull($this->refusalOf($this->cartOf($buyer, '1000', currency: $twoPerEuro)));
        self::assertInstanceOf(CompanyOrderLimitExceededException::class, $this->refusalOf($this->cartOf($buyer, '1002', currency: $twoPerEuro)));
    }

    public function testTheOrdersOfThePeriodAreAddedInTheDefaultCurrencyToo(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        // 3 600 in a currency worth half an euro: 1 800 euros.
        $this->orderInTheBooks($buyer, $company, '3600', ['currencyRate' => 2.0]);

        self::assertInstanceOf(CompanyPeriodLimitExceededException::class, $this->refusalOf($this->cartOf($buyer, '300')));
        self::assertNull($this->refusalOf($this->cartOf($buyer, '150')));
    }

    public function testAnOrderIsCountedForWhatItWasChargedPostageIncludedAndDiscountRemoved(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        // 700 of goods, 100 of postage, 50 of discount: the buyer was charged 750.
        $order = $this->orderInTheBooks($buyer, $company, '700', ['postage' => '100']);
        $order->setDiscount('50')->save($this->getPropelConnection());

        $since = new \DateTimeImmutable('first day of this month 00:00:00');

        self::assertSame('750.000000', (new CompanySpendingReader())->spentSince((int) $company->getId(), (int) $buyer->getId(), $since));
        // 750 + 300 = 1 050, and the buyer may spend 2 000 over the month.
        self::assertNull($this->refusalOf($this->cartOf($buyer, '300')));
    }

    public function testOnlyTheOrdersTheBuyerPlacedForTheirCompanyAreCounted(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $colleague = $this->buyerOf($company);
        $other = $this->company('Other');
        $this->orderInTheBooks($colleague, $company, '1800');
        $this->orderInTheBooks($buyer, $other, '1800');
        $this->orderInTheBooks($buyer, null, '1800');

        // A colleague's orders, the orders the buyer placed for another company and the ones of their own account are not theirs to this company.
        self::assertNull($this->refusalOf($this->cartOf($buyer, '300')));
    }

    public function testOnlyTheOrdersOfTheCurrentPeriodAreCounted(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company, orderLimit: '500', periodLimit: '600', period: 'week');
        // Wednesday 18 November 2026: the week started on Monday the 16th at midnight.
        $clock = new MockClock('2026-11-18 10:00:00');
        $this->orderInTheBooks($buyer, $company, '500', createdAt: new \DateTimeImmutable('2026-11-15 23:59:59'));

        self::assertNull($this->refusalOf($this->cartOf($buyer, '400'), $clock), 'Sunday night belongs to the week before.');

        $this->orderInTheBooks($buyer, $company, '500', createdAt: new \DateTimeImmutable('2026-11-16 00:00:00'));

        self::assertInstanceOf(CompanyPeriodLimitExceededException::class, $this->refusalOf($this->cartOf($buyer, '400'), $clock), 'Monday midnight belongs to this one.');
    }

    public function testASuspendedCompanyPlacesNothing(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->facade->suspend($company);

        self::assertInstanceOf(CompanySuspendedException::class, $this->refusalOf($this->cartOf($buyer, '1')));
    }

    public function testARoleWithoutThePermissionToOrderPlacesNothing(): void
    {
        $company = $this->company();
        $approver = $this->buyerOf($company, 'approver');

        self::assertInstanceOf(CompanyOrderNotPermittedException::class, $this->refusalOf($this->cartOf($approver, '1')));
    }

    public function testTheStepIsLeftOutOfTheTunnelOfACustomerWithoutACompany(): void
    {
        $customer = $this->customer();
        $cart = $this->cartOf($customer, '5000');

        self::assertTrue($this->provider()->isSkippedFor($cart));
        self::assertNull($this->refusalOf($cart));
        self::assertSame([], $this->provider()->collectRefusals($cart));
    }

    public function testTheStepIsShownToTheBuyerOfACompany(): void
    {
        [$buyer] = $this->limitedBuyer();

        self::assertFalse($this->provider()->isSkippedFor($this->cartOf($buyer, '10')));
    }

    public function testACartNobodyOwnsIsLeftOutToo(): void
    {
        $cart = $this->cartOf($this->customer(), '10');
        $cart->setCustomerId(null)->save($this->getPropelConnection());

        self::assertTrue($this->provider()->isSkippedFor($cart));
    }

    public function testACartNotPricedYetIsOnlyJudgedOnTheCompany(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $cart = $this->cartOf($buyer, '600');
        $cart->setDeliveryModuleId(null)->save($this->getPropelConnection());

        self::assertNull($this->refusalOf($cart), 'No carrier: the total is not known, and the placement judges the order once it is.');

        $this->facade->suspend($company);

        self::assertInstanceOf(CompanySuspendedException::class, $this->refusalOf($cart));
    }

    public function testTheApiReportsTheSameViolationAsTheStep(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->alreadyOrdered($buyer, $company, '1800');
        $cart = $this->cartOf($buyer, '600');

        $refusals = $this->provider()->collectRefusals($cart);
        $violations = array_values(array_filter(
            $this->getService(CheckoutValidationService::class)->collectViolations($cart),
            static fn ($violation): bool => CompanySpendingStepProvider::CODE === $violation->stepCode,
        ));

        self::assertCount(2, $refusals, 'The order limit and the month are both over.');
        self::assertCount(2, $violations);
        self::assertSame('company-order-limit-exceeded', $violations[0]->code);
        self::assertSame('company-period-limit-exceeded', $violations[1]->code);
        self::assertSame($refusals[0]->getMessage(), $violations[0]->message);
        self::assertSame(['limit' => '500', 'total' => '600'], $violations[0]->details);
    }

    public function testTheValidationOfTheTunnelRefusesWithTheFirstOfThem(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->alreadyOrdered($buyer, $company, '1800');
        $cart = $this->cartOf($buyer, '300');

        $this->expectException(CompanyPeriodLimitExceededException::class);

        foreach ($this->getService(CheckoutProgressionService::class)->providersInTunnelOrder() as $provider) {
            if (CompanySpendingStepProvider::CODE === $provider->code()) {
                $provider->check($cart);
            }
        }
    }

    public function testTheStepIsDeclaredBetweenTheDeliveryAndThePayment(): void
    {
        $codes = array_map(
            static fn (CheckoutStepProviderInterface $provider): string => $provider->code(),
            $this->getService(CheckoutProgressionService::class)->providersInTunnelOrder(),
        );

        self::assertSame(['cart', 'delivery', 'company.spending_limit', 'payment', 'confirmation'], $codes);
        self::assertInstanceOf(CheckoutStepViolationCollectorInterface::class, $this->provider());
    }

    /**
     * @return array{0: Customer, 1: Company}
     */
    private function limitedBuyer(): array
    {
        $company = $this->company();

        return [$this->buyerOf($company, orderLimit: '500.000000', periodLimit: '2000.000000', period: 'month'), $company];
    }

    private function alreadyOrdered(Customer $buyer, Company $company, string $amount): \Thelia\Model\Order
    {
        return $this->orderInTheBooks($buyer, $company, $amount);
    }

    private function refusalOf(Cart $cart, ?ClockInterface $clock = null): ?CheckoutException
    {
        try {
            $this->provider($clock)->check($cart);
        } catch (CheckoutException $refusal) {
            return $refusal;
        }

        return null;
    }

    private function provider(?ClockInterface $clock = null): CompanySpendingStepProvider
    {
        return new CompanySpendingStepProvider(
            $this->reader,
            new CompanySpendingJudge($this->getService(FormatService::class)),
            new CompanySpendingReader(),
            $this->getService(ExpressCheckoutTotal::class),
            $clock ?? $this->getService(ClockInterface::class),
        );
    }
}
