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

namespace Thelia\Tests\Api\Front;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;

/**
 * Paying a cart whose payment is split between an instant payment module and a provider,
 * over `POST /front/account/checkout/{cartId}/place`. The cart is worth 100.00.
 */
final class CheckoutSplitPaymentSettlementApiTest extends ApiTestCase
{
    use UsesInstantPaymentModules;

    private FixtureFactory $factory;

    private Module $instantModule;

    private Module $balanceChargingModule;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $container = static::getContainer();
        $connection = $this->getPropelConnection();

        $this->instantModule = $this->installPaymentModule(InstantTestPaymentModule::class, $container, $connection);
        $this->balanceChargingModule = $this->installPaymentModule(BalanceChargingTestPaymentModule::class, $container, $connection);
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->uninstallThePaymentModules(static::getContainer());
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    public function testAnInstantMeansThatRefusesIsAConflictAndTheOrderStaysUnpaidWithItsPartsPending(): void
    {
        [$cart, $token] = $this->checkoutReadyToPlace($this->balanceChargingModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        InstantTestPaymentModule::$refusesEveryPart = true;

        $placement = $this->jsonRequest('POST', $this->checkoutUrl($cart, 'place'), token: $token);

        self::assertSame(409, $placement->getStatusCode(), (string) $placement->getContent());
        self::assertStringNotContainsString('refuses', (string) $placement->getContent(), 'What the module said does not reach the buyer.');
        $order = OrderQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());
        self::assertNotNull($order);
        self::assertFalse($order->isPaid(false));
        self::assertSame(
            ['pending', 'pending'],
            array_column(OrderPaymentPartQuery::create()->filterByOrderId($order->getId())->orderByPosition()->find($this->getPropelConnection())->toArray(), 'State'),
        );
        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds, 'The provider was never asked.');
    }

    public function testAnInstantShareThatCoversTheCartPaysTheOrderWithoutAskingTheModuleToPay(): void
    {
        [$cart, $token] = $this->checkoutReadyToPlace($this->instantModule);
        InstantTestPaymentModule::$appliedAmount = '120';

        $placement = $this->jsonRequest('POST', $this->checkoutUrl($cart, 'place'), token: $token);

        self::assertSame(200, $placement->getStatusCode(), (string) $placement->getContent());
        $order = OrderQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());
        self::assertNotNull($order);
        self::assertTrue($order->isPaid(false));
        self::assertSame($this->instantModule->getId(), $order->getPaymentModuleId());
        self::assertSame(['settle'], InstantTestPaymentModule::$calls);
    }

    /**
     * @return array{0: Cart, 1: string}
     */
    private function checkoutReadyToPlace(Module $paymentModule): array
    {
        $this->turnMandatoryConsentsOff();

        $country = $this->factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $area = (new Area())->setName('Split payment placement test area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($deliveryModule->getId())->save($this->getPropelConnection());

        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });

        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => 'password']);
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product, overrides: ['price' => '100.000000', 'promoPrice' => '100.000000']);
        $address = $this->factory->address($customer, $country);

        $token = $this->authenticateAsCustomer($customer);
        foreach ([
            'delivery_address' => ['addressId' => $address->getId()],
            'invoice_address' => ['addressId' => $address->getId()],
            'delivery_module' => ['deliveryModuleId' => $deliveryModule->getId()],
            'payment_module' => ['paymentModuleId' => $paymentModule->getId()],
        ] as $step => $choice) {
            self::assertJsonResponseSuccessful($this->jsonRequest('POST', $this->checkoutUrl($cart, $step), $choice, token: $token));
        }

        return [$cart, $token];
    }

    private function turnMandatoryConsentsOff(): void
    {
        // Propel pools its objects for the whole process, and the transaction of the
        // previous test took its writes back without telling the pool.
        ConsentTableMap::clearInstancePool();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function checkoutUrl(Cart $cart, string $step): string
    {
        return '/api/front/account/checkout/'.$cart->getId().'/'.$step;
    }
}
