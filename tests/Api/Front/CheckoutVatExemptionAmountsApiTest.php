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
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Model\Address;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\AbstractDeliveryModuleWithState;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

final class CheckoutVatExemptionAmountsApiTest extends ApiTestCase
{
    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var array<string, Country> */
    private array $countries = [];

    private ?TaxRule $twentyPercentInFrance = null;

    private ?FixtureFactory $fixtureFactory = null;

    protected function setUp(): void
    {
        parent::setUp();

        ConsentTableMap::clearInstancePool();
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }
        $this->getService(ConsentProvider::class)->forgetCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(ConsentProvider::class)->forgetCache();
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testTheApiTunnelRepricesPostageAndTaxesAtEachInvoiceAddressChange(): void
    {
        $checkout = $this->checkout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $this->post($cartId, 'delivery_address', ['addressId' => $checkout['frenchAddress']->getId()], $token);
        $this->post($cartId, 'invoice_address', ['addressId' => $checkout['frenchAddress']->getId()], $token);
        $cart = $this->post($cartId, 'delivery_module', ['deliveryModuleId' => $checkout['deliveryModule']->getId()], $token);
        $this->assertResponseFigures($cart, false, 12.0, 2.0, 20.0);

        $cart = $this->post($cartId, 'invoice_address', ['addressId' => $checkout['belgianAddress']->getId()], $token);
        $this->assertResponseFigures($cart, true, 10.0, 0.0, 0.0);

        $cart = $this->post($cartId, 'invoice_address', ['addressId' => $checkout['frenchAddress']->getId()], $token);
        $this->assertResponseFigures($cart, false, 12.0, 2.0, 20.0);

        $cart = $this->post($cartId, 'invoice_address', ['addressId' => $checkout['belgianAddress']->getId()], $token);
        $this->assertResponseFigures($cart, true, 10.0, 0.0, 0.0);

        $this->post($cartId, 'payment_module', ['paymentModuleId' => $checkout['paymentModule']->getId()], $token);
        $order = $this->place($cartId, $token);

        $this->assertOrderFigures($order, 110.0, 0.0, 10.0, 0.0);
        self::assertEqualsWithDelta(
            20.0 + $this->postageVat($checkout['deliveryModule']),
            (float) $order->getOrderAddressRelatedByInvoiceOrderAddressId()->getVatExemptedAmount(),
            0.001,
        );
    }

    public function testAnApiOrderPlacedAfterTheVerificationExpiredCarriesNoUntaxedPostage(): void
    {
        $checkout = $this->checkout();
        $token = $this->authenticateAsCustomer($checkout['customer']);
        $cartId = $checkout['cart']->getId();

        $this->post($cartId, 'delivery_address', ['addressId' => $checkout['frenchAddress']->getId()], $token);
        $this->post($cartId, 'invoice_address', ['addressId' => $checkout['belgianAddress']->getId()], $token);
        $cart = $this->post($cartId, 'delivery_module', ['deliveryModuleId' => $checkout['deliveryModule']->getId()], $token);
        $this->assertResponseFigures($cart, true, 10.0, 0.0, 0.0);
        $this->post($cartId, 'payment_module', ['paymentModuleId' => $checkout['paymentModule']->getId()], $token);

        $this->ageVerificationOf($checkout['cart'], $checkout['belgianAddress'], ConfigQuery::getVatVerificationLifetimeDays() + 1);

        $order = $this->place($cartId, $token);

        self::assertSame(0, $order->getOrderAddressRelatedByInvoiceOrderAddressId()->getVatExempted(), 'Control: the order is no longer exempted.');
        $this->assertOrderFigures($order, 132.0, 22.0, 12.0, 2.0);
    }

    /**
     * @return array{cart: Cart, customer: Customer, deliveryModule: Module, paymentModule: Module, belgianAddress: Address, frenchAddress: Address}
     */
    private function checkout(): array
    {
        $factory = $this->factory();
        $france = $this->countryOf('FR');
        $belgium = $this->countryOf('BE');

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $france->getId());
        ConfigQuery::write('taxrule_id_delivery_module', (string) $this->twentyPercentInFrance()->getId());

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed - run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No Cheque module installed - run bin/test-prepare.');

        $area = (new Area())->setName('Recette montants '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($france->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($deliveryModule->getId())->save($this->getPropelConnection());

        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(12.0, 2.0, 'VAT 20'));
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });

        $title = $factory->customerTitle();
        $customer = $factory->customer($title, ['password' => 'password']);
        $frenchAddress = $factory->address($customer, $france, $title);
        $belgianAddress = $factory->address($customer, $belgium, $title, ['zipcode' => '1000', 'city' => 'Bruxelles']);
        $this->getPropelConnection()
            ->prepare('UPDATE `address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ? WHERE `id` = ?')
            ->execute(['Acme', 'BE0123456789', (new \DateTime('-10 days'))->format('Y-m-d H:i:s'), 'Acme SPRL', $belgianAddress->getId()]);
        AddressTableMap::clearInstancePool();
        $belgianAddress->reload();

        $product = $factory->product(
            $factory->category(),
            $this->twentyPercentInFrance(),
            $factory->currency(),
            ['baseQuantity' => 100, 'basePrice' => 100.0],
        );
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $product, null, ['price' => '100.000000', 'promoPrice' => '100.000000']);

        return [
            'cart' => $cart,
            'customer' => $customer,
            'deliveryModule' => $deliveryModule,
            'paymentModule' => $paymentModule,
            'belgianAddress' => $belgianAddress,
            'frenchAddress' => $frenchAddress,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function post(int $cartId, string $step, array $payload, string $token): array
    {
        $response = $this->jsonRequest('POST', '/api/front/account/checkout/'.$cartId.'/'.$step, $payload, token: $token);
        self::assertJsonResponseSuccessful($response);

        return $this->decode($response);
    }

    private function place(int $cartId, string $token): Order
    {
        $response = $this->jsonRequest('POST', '/api/front/account/checkout/'.$cartId.'/place', token: $token);
        self::assertJsonResponseSuccessful($response);

        $order = OrderQuery::create()->findPk($this->decode($response)['orderId'], $this->getPropelConnection());
        self::assertInstanceOf(Order::class, $order);

        return $order;
    }

    /**
     * @param array<string, mixed> $cart
     */
    private function assertResponseFigures(array $cart, bool $exempted, float $postage, float $postageTax, float $taxes): void
    {
        self::assertSame($exempted, $cart['isVatExempted'] ?? null, 'isVatExempted');
        self::assertEqualsWithDelta($postage, (float) $cart['postage'], 0.001, 'postage');
        self::assertEqualsWithDelta($postageTax, (float) $cart['postageTax'], 0.001, 'postageTax');
        self::assertEqualsWithDelta($taxes, (float) $cart['taxes'], 0.001, 'taxes (lignes)');
        self::assertEqualsWithDelta(100.0 + $taxes, (float) $cart['total'], 0.001, 'total (lignes TTC)');
    }

    private function assertOrderFigures(Order $order, float $total, float $vat, float $postage, float $postageTax): void
    {
        self::assertEqualsWithDelta($postage, (float) $order->getPostage(), 0.001, 'order.postage');
        self::assertEqualsWithDelta($postageTax, (float) $order->getPostageTax(), 0.001, 'order.postage_tax');
        $tax = 0.0;
        self::assertEqualsWithDelta($total, $order->getTotalAmount($tax), 0.001, 'Order::getTotalAmount');
        self::assertEqualsWithDelta($vat, $tax, 0.001, 'TVA de la commande');
    }

    private function ageVerificationOf(Cart $cart, Address $address, int $days): void
    {
        CartTableMap::clearInstancePool();
        $cart->reload();
        $verifiedAt = (new \DateTime(\sprintf('-%d days', $days)))->format('Y-m-d H:i:s');
        $connection = $this->getPropelConnection();
        $connection->prepare('UPDATE `cart_address` SET `vat_verified_at` = ? WHERE `id` = ?')
            ->execute([$verifiedAt, $cart->getAddressInvoiceId()]);
        $connection->prepare('UPDATE `address` SET `vat_verified_at` = ? WHERE `id` = ?')
            ->execute([$verifiedAt, $address->getId()]);
        AddressTableMap::clearInstancePool();
        CartAddressTableMap::clearInstancePool();
    }

    private function postageVat(Module $deliveryModule): float
    {
        $module = $deliveryModule->createInstance();
        self::assertTrue($module instanceof AbstractDeliveryModule || $module instanceof AbstractDeliveryModuleWithState);
        $vat = (float) $module->buildOrderPostage(10.0, $this->countryOf('FR'), 'en_US')->getAmountTax();
        self::assertGreaterThan(0.0, $vat, 'Control: the delivery module must tax its postage.');

        return $vat;
    }

    private function twentyPercentInFrance(): TaxRule
    {
        if (null !== $this->twentyPercentInFrance) {
            return $this->twentyPercentInFrance;
        }

        $factory = $this->factory();
        $taxRule = $factory->taxRule(['isDefault' => false]);
        $tax = $factory->tax(['requirements' => ['percent' => '20'], 'title' => 'VAT 20']);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($this->countryOf('FR')->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $this->twentyPercentInFrance = $taxRule;
    }

    private function countryOf(string $isoAlpha2): Country
    {
        return $this->countries[$isoAlpha2] ??= $this->factory()->country([
            'isocode' => $isoAlpha2,
            'isoalpha2' => $isoAlpha2,
            'isoalpha3' => $isoAlpha2.'X',
            'shopCountry' => 'FR' === $isoAlpha2,
        ]);
    }

    private function factory(): FixtureFactory
    {
        return $this->fixtureFactory ??= $this->createFixtureFactory();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function listen(string $eventName, callable $listener, int $priority = 512): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }
}
