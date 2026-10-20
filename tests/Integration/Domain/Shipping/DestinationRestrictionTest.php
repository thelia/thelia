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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Exception\DestinationRestrictedException;
use Thelia\Domain\Shipping\DestinationRestriction\CartAdditionDestinationGuard;
use Thelia\Domain\Shipping\DestinationRestriction\Destination;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;
use Thelia\Domain\Shipping\DestinationRestriction\ProductDestinationRestrictionReader;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\DestinationRestriction;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Product;
use Thelia\Model\StateQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A product of a destination restriction group is refused where its group says it does
 * not ship: at the cart when the address is known, at the address step, and at the
 * placement of the order, which is the refusal that always holds.
 */
final class DestinationRestrictionTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;
    private Country $refused;
    private Country $served;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        [$this->refused, $this->served] = CountryQuery::create()->filterByHasStates(0)->orderById()->limit(2)->find()->getData();
        $this->getService(DestinationRestrictionChecker::class)->reset();
    }

    protected function tearDown(): void
    {
        // The config cache is static: it would carry the language setting of a test
        // past the rollback, into the rest of the suite.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAProductIsRefusedToACountryItsGroupRefuses(): void
    {
        $product = $this->product('Calvados');
        $this->group('alcohol', DestinationRestriction::MODE_DENY, [$this->refused], [$product], 'Alcohol licence');

        $lines = $this->checker()->violationsForProducts([$product->getId()], new Destination($this->refused->getId()), null, 'en_US');

        self::assertCount(1, $lines);
        self::assertSame('Calvados (Alcohol licence)', $lines[0]->label());
        self::assertSame([], $this->checker()->violationsForProducts([$product->getId()], new Destination($this->served->getId()), null, 'en_US'));
    }

    public function testAGroupOfOnlyServedCountriesRefusesEveryOtherCountry(): void
    {
        $product = $this->product('Licensed board game');
        $this->group('licence', DestinationRestriction::MODE_ALLOW, [$this->served], [$product]);

        self::assertCount(1, $this->checker()->violationsForProducts([$product->getId()], new Destination($this->refused->getId()), null, 'en_US'));
        self::assertSame([], $this->checker()->violationsForProducts([$product->getId()], new Destination($this->served->getId()), null, 'en_US'));
    }

    public function testACountryCoversItsRegionsAndARegionCoversOnlyItself(): void
    {
        $state = StateQuery::create()->orderById()->findOne() ?? self::markTestSkipped('The test shop has no region.');
        $otherState = StateQuery::create()->filterByCountryId($state->getCountryId())->filterById($state->getId(), \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)->findOne()
            ?? self::markTestSkipped('The test shop has a single region in that country.');
        $wholeCountry = $this->product('Aerosol');
        $oneRegion = $this->product('Fireworks');
        $this->group('aerosol', DestinationRestriction::MODE_DENY, [$state->getCountry()], [$wholeCountry]);
        $fireworks = $this->group('fireworks', DestinationRestriction::MODE_DENY, [], [$oneRegion]);
        $this->facade()->addDestination($fireworks->getId(), (int) $state->getCountryId(), (int) $state->getId());

        $inTheRegion = new Destination((int) $state->getCountryId(), (int) $state->getId());
        $elsewhereInTheCountry = new Destination((int) $state->getCountryId(), (int) $otherState->getId());

        self::assertCount(2, $this->checker()->violationsForProducts([$wholeCountry->getId(), $oneRegion->getId()], $inTheRegion, null, 'en_US'));
        $lines = $this->checker()->violationsForProducts([$wholeCountry->getId(), $oneRegion->getId()], $elsewhereInTheCountry, null, 'en_US');
        self::assertSame([$wholeCountry->getId()], array_map(static fn ($line): int => $line->productId, $lines));
    }

    public function testADownloadIsJudgedOnTheInvoiceAddress(): void
    {
        $download = $this->product('E-book');
        $download->setVirtual(1)->save();
        $this->group('ebook', DestinationRestriction::MODE_DENY, [$this->refused], [$download]);

        self::assertSame([], $this->checker()->violationsForProducts([$download->getId()], new Destination($this->refused->getId()), new Destination($this->served->getId()), 'en_US'));
        self::assertCount(1, $this->checker()->violationsForProducts([$download->getId()], new Destination($this->served->getId()), new Destination($this->refused->getId()), 'en_US'));
    }

    public function testAProductTakenOutOfItsGroupShipsAgainAndItsSiblingDoesNot(): void
    {
        $first = $this->product('Rum');
        $second = $this->product('Gin');
        $group = $this->group('spirits', DestinationRestriction::MODE_DENY, [$this->refused], [$first, $second]);

        $this->facade()->detachProduct($group->getId(), $first->getId());

        $lines = $this->checker()->violationsForProducts([$first->getId(), $second->getId()], new Destination($this->refused->getId()), null, 'en_US');
        self::assertSame([$second->getId()], array_map(static fn ($line): int => $line->productId, $lines));
    }

    public function testAGroupTurnedOffRefusesNothing(): void
    {
        $product = $this->product('Cider');
        $group = $this->group('cider', DestinationRestriction::MODE_DENY, [$this->refused], [$product]);
        $this->facade()->save($group->getId(), 'cider', DestinationRestriction::MODE_DENY, false, []);
        // Another group is active, so the shop does check its carts.
        $this->group('perry', DestinationRestriction::MODE_DENY, [$this->refused], [$this->product('Perry')]);

        self::assertSame([], $this->checker()->violationsForProducts([$product->getId()], new Destination($this->refused->getId()), null, 'en_US'));
    }

    public function testAShopWithoutActiveGroupSpendsNoQueryOnTheCartsItChecks(): void
    {
        $cart = $this->cartOf(null, [$this->product('Plain chair')]);
        $this->checker()->hasActiveGroup();

        $statements = $this->recordSqlQueries(fn () => $this->checker()->violationsFor($cart, new Destination($this->refused->getId()), null, 'en_US'));

        self::assertSame([], $statements);
    }

    public function testAnAdditionIsRefusedWhenTheKnownAddressIsRefused(): void
    {
        $product = $this->product('Absinthe');
        $this->group('absinthe', DestinationRestriction::MODE_DENY, [$this->refused], [$product], 'Absinthe ban');
        $customer = $this->customerLivingIn($this->refused);
        $cart = $this->cartOf($customer, []);

        try {
            $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $product->getId(), (int) $product->getDefaultSaleElements()->getId()));
            self::fail('The product does not ship where the customer lives.');
        } catch (InvalidCartException $refusal) {
            self::assertStringContainsString('Absinthe (Absinthe ban)', $refusal->getMessage());
            self::assertStringNotContainsString((string) $this->refused->setLocale('en_US')->getTitle(), $refusal->getMessage());
        }

        self::assertSame(0, $cart->countCartItems(null, true));
    }

    public function testAnAdditionWithoutAKnownAddressIsAccepted(): void
    {
        $product = $this->product('Absinthe');
        $this->group('absinthe', DestinationRestriction::MODE_DENY, [$this->refused], [$product]);
        $cart = $this->cartOf(null, []);

        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $product->getId(), (int) $product->getDefaultSaleElements()->getId()));

        self::assertSame(1, $cart->countCartItems(null, true));
    }

    public function testARefusedDeliveryAddressNamesTheProductsOfTheCart(): void
    {
        $restricted = $this->product('Whisky');
        $this->group('whisky', DestinationRestriction::MODE_DENY, [$this->refused], [$restricted], 'Spirits');
        $customer = $this->customerLivingIn($this->served);
        $cart = $this->cartOf($customer, [$restricted, $this->product('Glass')]);
        $refusedAddress = $this->factory->address($customer, $this->refused);

        try {
            $this->getService(CartSelectionService::class)->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $refusedAddress->getId()));
            self::fail('The address cannot receive the whisky.');
        } catch (DestinationRestrictedException $refusal) {
            self::assertSame(CheckoutViolationCode::DeliveryDestinationRestricted->value, $refusal->violationCode());
            self::assertSame([$restricted->getId()], $refusal->violationDetails()['productIds']);
            self::assertStringContainsString('Whisky (Spirits)', $refusal->getMessage());
            self::assertStringContainsString('Remove it from your cart', $refusal->getMessage());
        }
    }

    public function testAnOrderIsRefusedWhenARestrictionCameAfterTheAddressWasChosen(): void
    {
        $product = $this->product('Brandy');
        $cart = $this->cartOf(null, [$product]);
        $carrier = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne();
        self::assertNotNull($carrier);
        $cart->setAddressDeliveryId($this->factory->cartAddress(null, $this->refused)->getId())->setDeliveryModuleId($carrier->getId())->save();

        // The group arrives once the cart already points at the refused country.
        $this->group('brandy', DestinationRestriction::MODE_DENY, [$this->refused], [$product]);

        $this->expectException(DestinationRestrictedException::class);
        $this->getService(CartGuard::class)->checkValidDelivery($cart);
    }

    public function testAReasonAndATitleWrittenInTheDefaultLanguageOnlyNameTheProductInAnotherOneWhenTheShopFallsBack(): void
    {
        [$defaultLocale, $otherLocale] = $this->defaultAndOtherLocale();
        $this->setTranslationFallback(Lang::REPLACE_BY_DEFAULT_LANGUAGE);
        $product = $this->product('Calvados', $defaultLocale);
        $this->group('alcohol', DestinationRestriction::MODE_DENY, [$this->refused], [$product], 'Alcohol licence', $defaultLocale);

        $lines = $this->checker()->violationsForProducts([$product->getId()], new Destination($this->refused->getId()), null, $otherLocale);

        self::assertCount(1, $lines);
        self::assertSame('Calvados (Alcohol licence)', $lines[0]->label());
    }

    public function testAShopKeptToTheRequestedLanguageNamesTheProductByItsReferenceWithoutReason(): void
    {
        [$defaultLocale, $otherLocale] = $this->defaultAndOtherLocale();
        $this->setTranslationFallback(Lang::STRICTLY_USE_REQUESTED_LANGUAGE);
        $product = $this->product('Calvados', $defaultLocale);
        $this->group('alcohol', DestinationRestriction::MODE_DENY, [$this->refused], [$product], 'Alcohol licence', $defaultLocale);

        $lines = $this->checker()->violationsForProducts([$product->getId()], new Destination($this->refused->getId()), null, $otherLocale);

        self::assertCount(1, $lines);
        self::assertSame($product->getRef(), $lines[0]->label());
    }

    public function testTheProductPageReadsTheReasonAndTheCountryNamesInTheDefaultLanguageWhenTheShopFallsBack(): void
    {
        [$defaultLocale, $otherLocale] = $this->defaultAndOtherLocale();
        $country = $this->factory->country(['isocode' => '998', 'isoalpha2' => 'QZ', 'isoalpha3' => 'QZQ']);
        $country->setLocale($defaultLocale)->setTitle('Atlantis')->save();
        $product = $this->product('Calvados');
        $this->group('alcohol', DestinationRestriction::MODE_DENY, [$country], [$product], 'Alcohol licence', $defaultLocale);
        $reader = $this->getService(ProductDestinationRestrictionReader::class);

        $this->setTranslationFallback(Lang::REPLACE_BY_DEFAULT_LANGUAGE);
        $groups = $reader->forProduct((int) $product->getId(), $otherLocale);

        self::assertCount(1, $groups);
        self::assertSame('Alcohol licence', $groups[0]['reason']);
        self::assertSame(['Atlantis'], $groups[0]['destinations']);

        $this->setTranslationFallback(Lang::STRICTLY_USE_REQUESTED_LANGUAGE);
        $groups = $reader->forProduct((int) $product->getId(), $otherLocale);

        self::assertSame('', $groups[0]['reason']);
        self::assertSame([(string) $country->getId()], $groups[0]['destinations']);
    }

    public function testADeliveryAddressServingTheParcelsIsAcceptedForADownloadBeforeAnyInvoiceAddress(): void
    {
        $download = $this->download('E-book');
        $this->group('ebook', DestinationRestriction::MODE_DENY, [$this->refused], [$download]);
        // The customer's default address lies in the refused country, but the cart has no invoice address yet.
        $customer = $this->customerLivingIn($this->refused);
        $cart = $this->cartOf($customer, [$download]);
        $servedAddress = $this->factory->address($customer, $this->served);

        $this->getService(CartSelectionService::class)->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $servedAddress->getId()));

        self::assertNotNull($cart->getAddressDeliveryId());
    }

    public function testAnOrderIsRefusedWhenTheInvoiceAddressOfTheCartIsRefusedToADownload(): void
    {
        $download = $this->download('E-book');
        $this->group('ebook', DestinationRestriction::MODE_DENY, [$this->refused], [$download]);
        $cart = $this->cartOf(null, [$download]);
        $cart->setAddressDeliveryId($this->factory->cartAddress(null, $this->served)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $this->refused)->getId())
            ->setDeliveryModuleId($this->carrier()->getId())
            ->save();

        $this->expectException(DestinationRestrictedException::class);
        $this->getService(CartGuard::class)->checkValidDelivery($cart);
    }

    public function testThePlacementDoesNotJudgeADownloadOnTheDefaultAddressOfTheCustomer(): void
    {
        $download = $this->download('E-book');
        $this->group('ebook', DestinationRestriction::MODE_DENY, [$this->refused], [$download]);
        $customer = $this->customerLivingIn($this->refused);
        $cart = $this->cartOf($customer, [$download]);
        $cart->setAddressDeliveryId($this->factory->cartAddress(null, $this->served)->getId())
            ->setDeliveryModuleId($this->carrier()->getId())
            ->save();
        $refusal = null;

        try {
            $this->getService(CartGuard::class)->checkValidDelivery($cart);
        } catch (CheckoutException $exception) {
            // The carrier may still refuse this cart: only the destination refusal matters here.
            $refusal = $exception;
        }

        self::assertNotInstanceOf(DestinationRestrictedException::class, $refusal);
    }

    public function testTheAddressOfAnotherCustomerTellsNothingAboutItsCountry(): void
    {
        $restricted = $this->product('Whisky');
        $this->group('whisky', DestinationRestriction::MODE_DENY, [$this->refused], [$restricted], 'Spirits');
        $cart = $this->cartOf($this->customerLivingIn($this->served), [$restricted]);
        $addressOfSomeoneElse = $this->factory->address($this->customerLivingIn($this->served), $this->refused);

        $this->getService(CartSelectionService::class)->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: $addressOfSomeoneElse->getId()));

        self::assertNull($cart->getAddressDeliveryId());
    }

    public function testTheReasonComesFromTheFirstRefusingGroupInTheOrderTheMerchantSet(): void
    {
        $product = $this->product('Absinthe');
        $this->group('created-first', DestinationRestriction::MODE_DENY, [$this->refused], [$product], 'Created first');
        $placedFirst = $this->group('placed-first', DestinationRestriction::MODE_DENY, [$this->refused], [$product], 'Placed first');
        $placedFirst->changeAbsolutePosition(1);

        $lines = $this->checker()->violationsForProducts([$product->getId()], new Destination($this->refused->getId()), null, 'en_US');

        self::assertCount(1, $lines);
        self::assertSame('Absinthe (Placed first)', $lines[0]->label());
    }

    public function testARefusedDestinationIsToldBeforeTheMissingCarrier(): void
    {
        $product = $this->product('Brandy');
        $this->group('brandy', DestinationRestriction::MODE_DENY, [$this->refused], [$product]);
        $cart = $this->cartOf(null, [$product]);
        // No carrier chosen yet: the product is still what keeps the order from this address.
        $cart->setAddressDeliveryId($this->factory->cartAddress(null, $this->refused)->getId())->save();

        $this->expectException(DestinationRestrictedException::class);
        $this->getService(CartGuard::class)->checkValidDelivery($cart);
    }

    public function testAddingAProductNoGroupHoldsCostsOneQueryWhileAnotherGroupIsActive(): void
    {
        $this->group('absinthe', DestinationRestriction::MODE_DENY, [$this->refused], [$this->product('Absinthe')]);
        $plain = $this->product('Plain chair');
        $customer = $this->customerLivingIn($this->served);
        // Read back, so that neither the customer nor the address is already in memory.
        $cart = CartQuery::create()->findPk($this->cartOf($customer, [])->getId());
        self::assertNotNull($cart);
        $this->checker()->hasActiveGroup();
        $event = (new CartEvent($cart))->setProductId((int) $plain->getId());

        $statements = $this->recordSqlQueries(fn () => $this->getService(CartAdditionDestinationGuard::class)->refuseARestrictedProduct($event));

        self::assertCount(1, $statements, implode("\n", $statements));
    }

    private function checker(): DestinationRestrictionChecker
    {
        return $this->getService(DestinationRestrictionChecker::class);
    }

    private function facade(): DestinationRestrictionFacade
    {
        return $this->getService(DestinationRestrictionFacade::class);
    }

    private function product(string $title, string $locale = 'en_US'): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['title' => $title, 'locale' => $locale]);
    }

    private function download(string $title): Product
    {
        $download = $this->product($title);
        $download->setVirtual(1)->save();

        return $download;
    }

    private function carrier(): Module
    {
        $carrier = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->orderById()->findOne();
        self::assertNotNull($carrier);

        return $carrier;
    }

    /**
     * @return array{string, string} the default locale of the shop, and another one
     */
    private function defaultAndOtherLocale(): array
    {
        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        return [$defaultLocale, 'fr_FR' === $defaultLocale ? 'en_US' : 'fr_FR'];
    }

    private function setTranslationFallback(int $mode): void
    {
        ConfigQuery::write('default_lang_without_translation', (string) $mode);

        $configs = [];

        foreach (ConfigQuery::create()->find() as $config) {
            $configs[$config->getName()] = $config->getValue();
        }

        ConfigQuery::initCache($configs);
    }

    /**
     * @param list<Country> $countries
     * @param list<Product> $products
     */
    private function group(string $code, int $mode, array $countries, array $products, string $reason = '', string $locale = 'en_US'): DestinationRestriction
    {
        $group = $this->facade()->save(null, $code, $mode, true, [$locale => ['title' => ucfirst($code), 'reason' => $reason]]);

        foreach ($countries as $country) {
            $this->facade()->addDestination($group->getId(), (int) $country->getId());
        }

        foreach ($products as $product) {
            $this->facade()->attachProduct($group->getId(), (int) $product->getId());
        }

        return $group;
    }

    private function customerLivingIn(Country $country): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country)->setIsDefault(1)->save();

        return $customer;
    }

    /**
     * @param list<Product> $products
     */
    private function cartOf(?Customer $customer, array $products): Cart
    {
        $cart = $this->factory->cart($customer);

        foreach ($products as $product) {
            $this->factory->cartItem($cart, $product);
        }

        return $cart;
    }
}
