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

namespace Thelia\Tests\Integration\Domain\Taxation;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Action\Coupon as CouponAction;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Legal\VatNumberVerifiedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Legal\VatVerificationResult;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItem;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;

/**
 * A verification lands on a cart already priced as taxed.
 *
 * The fixture is a French shop, a cart of one 10.00 line at 20 %, a 10 % coupon, and a
 * billing address in Belgium whose number is not verified yet. Taxed, the coupon takes
 * 1.20; exempt, it takes 1.00. The answer then arrives the way the SiretManagement
 * listener delivers it: a VAT_NUMBER_VERIFIED event, and nothing else.
 */
final class VatVerificationStaleCartTest extends ActionIntegrationTestCase
{
    use ForgetsPooledModels;

    private const NUMBER = 'BE0123456789';

    /** @var array<string, Country> */
    private array $countries = [];

    protected function tearDown(): void
    {
        self::forgetPooledModels();
        Propel::disableInstancePooling();
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    /**
     * Production runs with the instance pool on: the session cart is the very object
     * that read its billing copy while pricing the discount, and record() rewrites that
     * copy with a bulk UPDATE the object never hears about.
     */
    public function testACartAlreadyLoadedSeesTheVerificationRecordedOnItsBillingAddress(): void
    {
        Propel::enableInstancePooling();
        $this->configure();
        [$cart, $address] = $this->cartBilledToAnUnverifiedBelgianAddress();
        self::assertFalse($cart->isVatExempted(), 'Control: unverified, the cart is taxed.');

        $this->verify($address);

        self::assertTrue(
            $this->discountlessReadOfAFreshCart($cart)->isVatExempted(),
            'Control: read afresh from the database, the cart is exempt.',
        );
        self::assertTrue(
            $cart->isVatExempted(),
            'The cart object that priced the order before the answer must follow it: it keeps its stale billing copy.',
        );
    }

    public function testTheDiscountOfASessionCartIsPricedAgainOnceTheVerificationHasLanded(): void
    {
        Propel::enableInstancePooling();
        $this->configure();
        [$cart, $address, $customer, $currency] = $this->cartBilledToAnUnverifiedBelgianAddress();
        $this->consumeTenPercentCoupon($cart, $customer, $currency);
        self::assertEqualsWithDelta(1.2, $this->discountInDatabase($cart), 0.0001, 'Control: ten percent of the taxed 12.00.');

        $this->verify($address);
        // ADDRESS_POST_UPDATE reaches Action\Coupon::updateOrderDiscount() at priority 10.
        $this->getService(CouponAction::class)->updateOrderDiscount(new Event(), 'recette.address-post-update', $this->kernelDispatcher());

        self::assertEqualsWithDelta(
            1.0,
            $this->discountInDatabase($cart),
            0.0001,
            'The cart is exempt now: ten percent of the untaxed 10.00, not of the taxed 12.00.',
        );
    }

    public function testControlWithoutThePoolTheDiscountIsPricedAgainOnceTheVerificationHasLanded(): void
    {
        $this->configure();
        [$cart, $address, $customer, $currency] = $this->cartBilledToAnUnverifiedBelgianAddress();
        $this->consumeTenPercentCoupon($cart, $customer, $currency);

        $this->verify($address);
        $this->getService(CouponAction::class)->updateOrderDiscount(new Event(), 'recette.address-post-update', $this->kernelDispatcher());

        self::assertEqualsWithDelta(1.0, $this->discountInDatabase($cart), 0.0001);
    }

    /**
     * No theme is involved: the discount follows the exemption the answer just changed.
     */
    public function testTheCoreRepricesTheDiscountWhenAVerificationChangesTheExemption(): void
    {
        $this->configure();
        [$cart, $address, $customer, $currency] = $this->cartBilledToAnUnverifiedBelgianAddress();
        $this->consumeTenPercentCoupon($cart, $customer, $currency);
        self::assertEqualsWithDelta(1.2, $this->discountInDatabase($cart), 0.0001, 'Control: ten percent of the taxed 12.00.');

        $this->verify($address);

        self::assertEqualsWithDelta(
            1.0,
            $this->discountInDatabase($cart),
            0.0001,
            'The cart is exempt now: the discount must not stay at the taxed 1.20.',
        );
    }

    private function verify(Address $address): void
    {
        $this->kernelDispatcher()->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::verified(new \DateTimeImmutable(), 'ACME SPRL')),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );
    }

    private function discountlessReadOfAFreshCart(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();
        $fresh = CartQuery::create()->findPk($cart->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }

    private function discountInDatabase(Cart $cart): float
    {
        return (float) $this->getPropelConnection()
            ->query('SELECT discount FROM cart WHERE id = '.(int) $cart->getId())
            ->fetchColumn();
    }

    /**
     * @return array{0: Cart, 1: Address, 2: \Thelia\Model\Customer, 3: \Thelia\Model\Currency}
     */
    private function cartBilledToAnUnverifiedBelgianAddress(): array
    {
        $france = $this->countryOf('FR');
        $currency = $this->factory->currency();
        $title = $this->factory->customerTitle();
        $customer = $this->factory->customer($title);
        $product = $this->factory->product(
            $this->factory->category(),
            $this->taxRuleTaxingAt($france, '20'),
            $currency,
            ['baseQuantity' => 100],
        );

        $address = $this->factory->address($customer, $this->countryOf('BE'), $title, ['zipcode' => '1000', 'city' => 'Brussels']);
        $address->setCompany('Acme')->setVatNumber(self::NUMBER)->save($this->getPropelConnection());

        $cartAddressService = $this->getService(CartAddressService::class);
        $invoiceCopy = $cartAddressService->getOrCreateCartAddressFromAddress($address);
        $deliveryCopy = $cartAddressService->getOrCreateCartAddressFromAddress(
            $this->factory->address($customer, $france, $title),
        );
        self::assertInstanceOf(CartAddress::class, $invoiceCopy);

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken(uniqid('stale-copy-', true))
            ->setAddressDeliveryId($deliveryCopy->getId())
            ->setAddressInvoiceId($invoiceCopy->getId());
        $cart->save($this->getPropelConnection());

        $productSaleElements = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($productSaleElements);
        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($product->getId())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity(1)
            ->setPrice('10.00')
            ->setPromoPrice('10.00')
            ->setPromo(0)
            ->save($this->getPropelConnection());

        return [$cart, $address, $customer, $currency];
    }

    private function consumeTenPercentCoupon(Cart $cart, \Thelia\Model\Customer $customer, \Thelia\Model\Currency $currency): void
    {
        $session = $this->session();
        $session->setCustomerUser($customer);
        $session->setSessionCart($cart);
        $session->setCurrency($currency);
        $coupon = $this->factory->coupon([
            'code' => 'TEN-PERCENT-'.uniqid(),
            'type' => 'thelia.coupon.type.remove_x_percent',
            'effects' => ['percentage' => 10.0],
            'conditions' => $this->atLeastOneArticle(),
        ]);
        $session->setConsumedCoupons([$coupon->getCode()]);
        $this->getService(CouponAction::class)->updateOrderDiscount(new Event(), 'recette.recompute', $this->kernelDispatcher());
    }

    private function atLeastOneArticle(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => 1],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }

    private function taxRuleTaxingAt(Country $country, string $percent): TaxRule
    {
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        $tax = $this->factory->tax(['requirements' => ['percent' => $percent], 'title' => 'VAT '.$percent]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }

    private function configure(): void
    {
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('vat_verification_lifetime_days', '90');
        ConfigQuery::write('store_country', (string) $this->countryOf('FR')->getId());
    }

    private function countryOf(string $isoAlpha2): Country
    {
        return $this->countries[$isoAlpha2] ??= $this->factory->country([
            'isocode' => $isoAlpha2,
            'isoalpha2' => $isoAlpha2,
            'isoalpha3' => $isoAlpha2.'X',
        ]);
    }

    private function kernelDispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }

    private function session(): Session
    {
        return static::getContainer()->get('request_stack')->getCurrentRequest()->getSession();
    }
}
