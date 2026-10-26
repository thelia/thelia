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

namespace Thelia\Tests\Http\Flexy;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Model\Address;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;

/**
 * The delivery address edited from the customer account, the buyer going straight back to
 * the payment step without passing the delivery step.
 *
 * The cart ships to its own copy of the address, and its carrier and postage were quoted
 * for the place the copy names. Another street reaches the copy at once; another postal
 * code or city sends the buyer back to the delivery step, which asks the carriers again.
 */
final class DeliveryAddressEditedInTheAccountTest extends GuestCheckoutTestCase
{
    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessTheThemeHasTheIdentificationPage();

        foreach (ConsentQuery::create()->filterByActive(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setMandatory(0)->save($this->getPropelConnection());
        }

        static::getContainer()->get('Thelia\Domain\Checkout\Service\ConsentProvider')->forgetCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testAnAddressMovedInTheAccountSendsThePaymentStepBackToTheDeliveryStep(): void
    {
        [$cart, $address] = $this->anAccountStandingAtThePaymentStep();

        $this->editInTheAccount($address, ['zipcode' => '20000', 'city' => 'Ajaccio']);

        $this->client->request('GET', '/checkout/payment');
        $this->assertResponseRedirectsTo('/checkout/delivery');

        $this->client->request('GET', '/checkout/delivery');
        self::assertSame('20000', $this->deliveryCopyOf($cart)?->getZipcode(), 'The delivery step chooses the moved address again.');
    }

    public function testAStreetEditedInTheAccountReachesTheCartAndKeepsThePaymentStep(): void
    {
        [$cart, $address] = $this->anAccountStandingAtThePaymentStep();
        $carrierId = $cart->getDeliveryModuleId();

        $this->editInTheAccount($address, ['address1' => '2 avenue des Tests']);

        $this->client->request('GET', '/checkout/payment');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('2 avenue des Tests', $this->deliveryCopyOf($cart)?->getAddress1());
        self::assertSame($carrierId, CartQuery::create()->findPk($cart->getId())?->getDeliveryModuleId());
    }

    /**
     * A signed-in account with a cart whose address, carrier and payment are chosen, as
     * the delivery step leaves it. Control: the payment step opens.
     *
     * @return array{0: Cart, 1: Address}
     */
    private function anAccountStandingAtThePaymentStep(): array
    {
        $account = $this->signInAsARealAccount();
        $address = $this->fixtures()->address($account, $this->france(), $account->getCustomerTitle(), [
            'zipcode' => '69003',
            'city' => 'Lyon',
        ]);
        $cart = $this->cartOf($account);
        $connection = $this->getPropelConnection();

        $this->serveTheCountryOf($address);
        $this->answerTheDeliveryQuote();
        $this->acceptEveryPayment();

        $copies = $this->getService(CartAddressService::class);
        $cart
            ->setAddressDeliveryId($copies->getOrCreateCartAddressFromAddress($address)->getId())
            ->setAddressInvoiceId($copies->getOrCreateCartAddressFromAddress($address)->getId())
            ->setDeliveryModuleId($this->moduleNamed('CustomDelivery'))
            ->setPaymentModuleId($this->moduleNamed('Cheque'))
            ->setPostage('12.000000')
            ->setPostageTax('2.000000')
            ->setPostageTaxRuleTitle('VAT 20')
            ->save($connection);

        foreach ($cart->getCartItems() as $cartItem) {
            $cartItem->getProductSaleElements()->setQuantity(100)->save($connection);
        }

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        ProductSaleElementsTableMap::clearInstancePool();
        ProductSaleElementsTableMap::clearRelatedInstancePool();

        $this->client->request('GET', '/checkout/payment');
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'Control: the payment step must open before the address is edited.');

        return [$cart, $address];
    }

    /**
     * What the account form of the address dispatches once it has checked the address is
     * the buyer's (AddressService::updateAddress()). The form itself is not driven: the
     * service reads an autowired session, which the kernel of a test does not share with
     * the session of the browser.
     *
     * @param array<string, string> $changes
     */
    private function editInTheAccount(Address $address, array $changes): void
    {
        $event = new AddressCreateOrUpdateEvent(
            (string) $address->getLabel(),
            $address->getTitleId(),
            (string) $address->getFirstname(),
            (string) $address->getLastname(),
            $changes['address1'] ?? (string) $address->getAddress1(),
            (string) $address->getAddress2(),
            (string) $address->getAddress3(),
            $changes['zipcode'] ?? (string) $address->getZipcode(),
            $changes['city'] ?? (string) $address->getCity(),
            (int) $address->getCountryId(),
            $address->getCellphone(),
            $address->getPhone(),
            $address->getCompany(),
            (bool) $address->getIsDefault(),
        );
        $event->setAddress($address);

        $this->dispatcher()->dispatch($event, TheliaEvents::ADDRESS_UPDATE);
    }

    /**
     * The cart the signed-in session holds, with one line in it.
     *
     * Filled after the sign-in rather than before: the kernel is kept alive between the
     * requests of a test, and a service built by a request made before the sign-in would
     * keep the session of a visitor.
     */
    private function cartOf(Customer $account): Cart
    {
        $this->client->request('GET', '/checkout/cart');

        CartTableMap::clearInstancePool();
        $cart = CartQuery::create()->filterByCustomerId($account->getId())->orderById(Criteria::DESC)->findOne()
            ?? self::fail('The session holds no cart of the account.');

        $fixtures = $this->fixtures();
        $product = $fixtures->product($fixtures->category(), $fixtures->taxRule(), $fixtures->currency(), ['title' => 'A product in the cart']);
        $fixtures->cartItem($cart, $product);

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();

        return CartQuery::create()->findPk($cart->getId()) ?? self::fail('The cart is gone.');
    }

    private function deliveryCopyOf(Cart $cart): ?CartAddress
    {
        CartTableMap::clearInstancePool();
        CartAddressTableMap::clearInstancePool();

        return CartAddressQuery::create()->findPk(CartQuery::create()->findPk($cart->getId())?->getAddressDeliveryId());
    }

    private function france(): Country
    {
        return CountryQuery::create()->findOneByIsoalpha2('FR') ?? self::fail('No France in the countries.');
    }

    private function serveTheCountryOf(Address $address): void
    {
        $connection = $this->getPropelConnection();

        $area = (new Area())->setName('Delivery address edited in the account test area');
        $area->save($connection);

        (new CountryArea())->setAreaId($area->getId())->setCountryId($address->getCountryId())->save($connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->moduleNamed('CustomDelivery'))->save($connection);
    }

    private function moduleNamed(string $code): int
    {
        return (int) (ModuleQuery::create()->findOneByCode($code)?->getId()
            ?? self::fail(\sprintf('No "%s" module installed — run bin/test-prepare.', $code)));
    }

    private function answerTheDeliveryQuote(): void
    {
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(12.0, 2.0, 'VAT 20'));
            $event->stopPropagation();
        });
    }

    private function acceptEveryPayment(): void
    {
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher()->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
