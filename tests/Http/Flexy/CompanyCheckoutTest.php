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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Company\Checkout\CompanySpendingStepProvider;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Address;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderStatus;

/**
 * A company the shop suspended: its buyers stay signed in and keep their history, the account says
 * why nothing can be ordered, and the checkout beyond the cart answers with the same sentence. And
 * the step the company judges the order in is not a stop of the bar of the checkout.
 */
final class CompanyCheckoutTest extends CompanyAccountTestCase
{
    private const SENTENCE = 'Your company account is suspended';

    /** @var list<array{0: string, 1: callable}> */
    private array $listeners = [];

    public function testTheBuyerOfASuspendedCompanyStaysSignedInWithABannerAndHisHistory(): void
    {
        $company = $this->company('Acme', active: false);
        $buyer = $this->buyer($company);
        $order = $this->factory->order($buyer, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setCompanyId($company->getId())->setPlacedByCustomerId($buyer->getId())->save($this->getPropelConnection());
        $this->signInAs($buyer);

        foreach (['/account', '/account/orders', '/account/addresses', '/account/company'] as $path) {
            $page = $this->get($path);

            self::assertSame(200, $this->statusCode(), $path);
            self::assertCount(1, $page->filter('[data-testid="company-suspended-banner"]'), $path);
        }

        self::assertStringContainsString((string) $order->getRef(), $this->get('/account/orders')->filter('body')->text());
    }

    public function testTheBannerIsNotShownToAnActiveCompanyNorToACustomerWithoutOne(): void
    {
        $this->signInAs($this->buyer($this->company()));
        self::assertCount(0, $this->get('/account')->filter('[data-testid="company-suspended-banner"]'));

        $this->signInAs($this->customer());
        self::assertCount(0, $this->get('/account')->filter('[data-testid="company-suspended-banner"]'));
    }

    public function testTheStepsOfTheCheckoutBeyondTheCartSendTheBuyerOfASuspendedCompanyBackToTheCartWithTheReason(): void
    {
        $buyer = $this->buyer($this->company('Acme', active: false));
        $this->signInWithCart($buyer, $this->factory->cart($buyer));

        foreach (['/checkout/delivery', '/checkout/payment', '/checkout/pay'] as $path) {
            $this->get($path);
            self::assertResponseRedirects('/checkout/cart', null, $path);

            $cart = $this->client->followRedirect();
            self::assertSame(200, $this->statusCode());
            self::assertStringContainsString(self::SENTENCE, $cart->filter('body')->text(), $path);
        }
    }

    public function testTheCheckoutIsNotCutShortForAnActiveCompany(): void
    {
        $buyer = $this->buyer($this->company());
        $this->signInWithCart($buyer, $this->factory->cart($buyer));

        $this->get('/checkout/delivery');

        $location = (string) $this->client->getResponse()->headers->get('Location');
        $cart = $this->client->followRedirect();
        self::assertStringNotContainsString(self::SENTENCE, $cart->filter('body')->text(), $location);
    }

    public function testTheSpendingStepOfTheCompanyIsNotAStopOfTheBar(): void
    {
        $buyer = $this->buyer($this->company(), CompanyRoleCode::BUYER);
        $cart = $this->factory->cart($buyer);
        $this->signInWithCart($buyer, $cart);

        $steps = $this->getService(CheckoutProgressionService::class)->activeSteps($cart);
        $codes = array_map(static fn ($step): string => $step->code, $steps);
        self::assertContains(CompanySpendingStepProvider::CODE, $codes, 'The tunnel of a buyer of a company holds the step: without it this proves nothing.');
        $title = null;

        foreach ($steps as $step) {
            if (CompanySpendingStepProvider::CODE === $step->code) {
                $title = $step->title;
            }
        }

        $page = $this->get('/checkout/cart');

        self::assertSame(200, $this->statusCode());
        self::assertNotEmpty($page->filter('.CheckoutSteps')->count(), 'The bar is drawn.');
        self::assertStringNotContainsString((string) $title, $page->filter('.CheckoutSteps')->text());
    }

    public function testAnOrderAboveTheLimitOfTheBuyerIsRefusedWithTheReasonAndOneWithinItIsNot(): void
    {
        $buyer = $this->buyer($this->company(), CompanyRoleCode::BUYER);
        $this->companies->changeLimits($buyer, '500', null, null);

        $this->signInWithCart($buyer, $this->cartAtThePaymentStep($buyer, '150'));
        $this->get('/checkout/payment');
        self::assertSame(200, $this->statusCode(), 'Control: an order within the limit goes on to the payment.');

        $this->signInWithCart($buyer, $this->cartAtThePaymentStep($buyer, '600'));
        $this->get('/checkout/payment');
        self::assertResponseRedirects();
        $refused = $this->client->followRedirect();

        self::assertStringContainsString('exceeds', $refused->filter('body')->text(), 'The sentence of the core is on the page the buyer lands on.');
        self::assertStringContainsString('500', $refused->filter('body')->text());
    }

    public function testThePaymentStepOffersToEditTheBillingAddressOnlyToWhoMayChangeIt(): void
    {
        $company = $this->company();
        $creator = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $colleague = $this->buyer($company, CompanyRoleCode::BUYER);
        $warehouse = $this->factory->address($creator, CountryQuery::create()->findOneByIsoalpha2('FR'), $creator->getCustomerTitle(), ['label' => 'Warehouse', 'zipcode' => '69003', 'city' => 'Lyon']);
        $warehouse->setCompanyId($company->getId())->save($this->getPropelConnection());

        $this->signInWithCart($colleague, $this->cartAtThePaymentStep($colleague, '150', $warehouse));
        $page = $this->get('/checkout/payment');

        self::assertSame(200, $this->statusCode());
        self::assertStringContainsString('Warehouse', $page->filter('body')->text(), 'Control: the shared address is the billing address of the colleague.');
        self::assertStringNotContainsString('Edit the address', $page->filter('body')->text(), 'The colleague may use the address, not change it.');

        $this->signInWithCart($creator, $this->cartAtThePaymentStep($creator, '150', $warehouse));
        $page = $this->get('/checkout/payment');

        self::assertStringContainsString('Edit the address', $page->filter('body')->text(), 'The one who made the address may change it.');
    }

    /**
     * A cart of one line with its carrier, its payment and its addresses chosen, as the delivery step
     * leaves it, for a buyer whose address is served by the carrier.
     */
    private function cartAtThePaymentStep(Customer $customer, string $price, ?Address $address = null): Cart
    {
        $connection = $this->getPropelConnection();
        $currency = $this->factory->currency();
        $address ??= $this->factory->address($customer, CountryQuery::create()->findOneByIsoalpha2('FR'), $customer->getCustomerTitle(), ['zipcode' => '69003', 'city' => 'Lyon']);
        $cart = $this->factory->cart($customer, ['currency' => $currency]);
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(['isDefault' => false]),
            $currency,
            ['baseQuantity' => 100],
        );
        $this->factory->cartItem($cart, $product, null, ['price' => $price, 'promoPrice' => $price]);

        $area = (new Area())->setName('Company checkout test area');
        $area->save($connection);
        (new CountryArea())->setAreaId($area->getId())->setCountryId($address->getCountryId())->save($connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->moduleId('CustomDelivery'))->save($connection);

        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(12.0, 2.0, 'VAT 20'));
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });

        $copies = $this->getService(CartAddressService::class);
        $cart
            ->setAddressDeliveryId($copies->getOrCreateCartAddressFromAddress($address)->getId())
            ->setAddressInvoiceId($copies->getOrCreateCartAddressFromAddress($address)->getId())
            ->setDeliveryModuleId($this->moduleId('CustomDelivery'))
            ->setPaymentModuleId($this->moduleId('Cheque'))
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

        return CartQuery::create()->findPk($cart->getId()) ?? $cart;
    }

    private function moduleId(string $code): int
    {
        return (int) (ModuleQuery::create()->findOneByCode($code)?->getId() ?? self::fail(\sprintf('No "%s" module installed.', $code)));
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, 512);
        $this->listeners[] = [$eventName, $listener];
    }

    protected function tearDown(): void
    {
        foreach ($this->listeners as [$eventName, $listener]) {
            $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
        }

        parent::tearDown();
    }
}
