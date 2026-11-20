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

use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Exception\CheckoutRefusedException;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Company\Checkout\CompanySpendingStepProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\Company;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;

/**
 * A company buyer placing an order the way the front API does, with nobody at a browser: the
 * company judges the cart before anything is written, and the order once it is.
 */
final class OrderSpendingCheckoutTest extends OrderCompanyTestCase
{
    /** @var list<array{string, callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }

        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    public function testACartWithinTheLimitsIsPlacedForTheCompany(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $cart = $this->readyCart($buyer, '150');

        $result = $this->placement()->place($this->requestFor($cart, $buyer));

        self::assertSame((int) $company->getId(), OrderQuery::create()->findPk($result->orderId)?->getCompanyId());
    }

    public function testASuspendedCompanyIsRefusedWithAMessageTheBuyerCanRead(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $cart = $this->readyCart($buyer, '150');
        $this->facade->suspend($company);
        $this->reader->reset();
        $this->getService(\Thelia\Domain\Company\Service\CompanyMembershipReader::class)->reset();

        try {
            $this->placement()->place($this->requestFor($cart, $buyer));
            self::fail('A suspended company placed an order.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertCount(1, $refusal->violations);
            self::assertSame(CompanySpendingStepProvider::CODE, $refusal->violations[0]->stepCode);
            self::assertSame('company-suspended', $refusal->violations[0]->code);
            self::assertStringContainsString('suspended', $refusal->violations[0]->message);
        }

        self::assertNull(OrderQuery::create()->filterByCartId($cart->getId())->findOne(), 'Nothing was written for the cart.');
    }

    public function testACartOverTheLimitIsRefusedByTheCheckoutBeforeAnythingIsWritten(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $this->orderInTheBooks($buyer, $company, '1800');
        $cart = $this->readyCart($buyer, '300');

        try {
            $this->placement()->place($this->requestFor($cart, $buyer));
            self::fail('An order over the limit of the period was placed.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame(['company-period-limit-exceeded'], array_map(static fn ($violation): string => $violation->code, $refusal->violations));
        }

        self::assertNull(OrderQuery::create()->filterByCartId($cart->getId())->findOne());
    }

    public function testALimitUsedUpBetweenTheCheckAndTheWritingIsAnsweredLikeTheCheckoutAnswersIt(): void
    {
        [$buyer, $company] = $this->limitedBuyer();
        $cart = $this->readyCart($buyer, '300');

        // The checkout read the month as empty; another cart of the same buyer is placed before this one is written.
        $this->listen(TheliaEvents::ORDER_PAY, function () use ($buyer, $company): void {
            $this->orderInTheBooks($buyer, $company, '1900');
        }, 1024);

        try {
            $this->placement()->place($this->requestFor($cart, $buyer));
            self::fail('The month went over its limit and the order was placed.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertCount(1, $refusal->violations);
            self::assertSame(CompanySpendingStepProvider::CODE, $refusal->violations[0]->stepCode);
            self::assertSame('company-period-limit-exceeded', $refusal->violations[0]->code);
            self::assertSame(['limit' => '2000', 'period' => 'month', 'spent' => '1900', 'total' => '300'], $refusal->violations[0]->details);
        }
    }

    /**
     * @return array{0: Customer, 1: Company}
     */
    private function limitedBuyer(): array
    {
        $company = $this->company();

        return [$this->buyerOf($company, orderLimit: '500.000000', periodLimit: '2000.000000', period: 'month'), $company];
    }

    private function readyCart(Customer $buyer, string $price): Cart
    {
        $country = $this->fixtures->country();
        $delivery = ModuleQuery::create()->findOneByCode('CustomDelivery') ?? throw new \RuntimeException('No delivery module installed.');
        $payment = ModuleQuery::create()->findOneByCode('Cheque') ?? throw new \RuntimeException('No payment module installed.');

        $this->serveCountryWith($delivery->getId(), $country);
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
            $event->stopPropagation();
        }, 512);
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        }, 512);

        $this->fixtures->address($buyer, $country);
        $cart = $this->cartOf($buyer, $price);
        $cart
            ->setAddressDeliveryId($this->fixtures->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->fixtures->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($delivery->getId())
            ->setPaymentModuleId($payment->getId())
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function serveCountryWith(int $moduleId, Country $country): void
    {
        $area = (new Area())->setName('Company checkout area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($moduleId)->save($this->getPropelConnection());
    }

    private function requestFor(Cart $cart, Customer $buyer): CheckoutPlacementRequest
    {
        return new CheckoutPlacementRequest($cart, $buyer, $this->fixtures->currency(), $this->fixtures->lang(), []);
    }

    private function placement(): CheckoutPlacementService
    {
        return $this->getService(CheckoutPlacementService::class);
    }

    private function listen(string $eventName, callable $listener, int $priority): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
