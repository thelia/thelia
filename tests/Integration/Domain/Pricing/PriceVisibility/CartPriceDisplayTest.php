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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceVisibility;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\Exception\CartLineUnavailableException;
use Thelia\Domain\Cart\Exception\ProductNotAvailableException;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Cart\Service\CartLinePricer;
use Thelia\Domain\Cart\Service\CartLineVisibility;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;

/**
 * The cart sells only what its visitor is shown the price of: an addition is refused whoever
 * asks (the facade the live components and the API call), a line that stopped being for sale
 * is taken out of the cart when it is loaded, and the checkout refuses the one still there.
 *
 * The example: the shop shows its prices to identified customers only. A visitor adds nothing; a
 * customer adds a sofa; the sofa goes on request, and the customer's cart loses it, whatever the
 * way the cart comes back.
 */
final class CartPriceDisplayTest extends ActionIntegrationTestCase
{
    use PriceDisplayFixtures;
    use PriceListFixtures;
    use RecordsSqlQueries;

    private Product $sofa;

    private Product $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sofa = $this->pricedProduct();
        $this->table = $this->pricedProduct();
        $this->forgetPriceDisplayMemos();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->session()->setSessionCart(null);
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->remove(ConfigQuery::read('cart.cookie_name', 'thelia_cart'));
        $this->forgetPriceDisplayMemos();

        parent::tearDown();
    }

    public function testAVisitorCannotAddWhatIsShownToIdentifiedCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $cart = $this->factory->cart();

        $this->assertRefused($cart, $this->sofa);

        self::assertSame(0, $this->lineCount($cart));
    }

    public function testACustomerAddsWhatIsShownToIdentifiedCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->signInAs($this->customer());
        $cart = $this->factory->cart();

        $this->add($cart, $this->sofa);

        self::assertSame(1, $this->lineCount($cart));
    }

    public function testNobodyAddsAProductOnRequestEvenInAShopThatShowsEveryPrice(): void
    {
        $this->productShows($this->sofa, PriceDisplayMode::OnRequest);
        $cart = $this->factory->cart();

        $this->assertRefused($cart, $this->sofa);
        $this->signInAs($this->customer());
        $this->assertRefused($cart, $this->sofa);
        $this->add($cart, $this->table);

        self::assertSame(1, $this->lineCount($cart), 'the other product of the shop is added');
    }

    public function testAProductMayBeBoughtInAShopThatIsOnRequest(): void
    {
        $this->shopShows(PriceDisplayMode::OnRequest);
        $this->productShows($this->sofa, PriceDisplayMode::Visible);
        $cart = $this->factory->cart();

        $this->add($cart, $this->sofa);
        $this->assertRefused($cart, $this->table);

        self::assertSame(1, $this->lineCount($cart));
    }

    /**
     * Acceptance 4: the exclusive list prices A and B, the catalog holds A, B and C.
     */
    public function testASaleElementAnExclusiveListDoesNotPriceCannotBeAdded(): void
    {
        $pseA = $this->sofa->getDefaultSaleElements();
        $pseC = $this->factory->productSaleElement($this->sofa);
        $this->factory->productPrice($pseC, Currency::getDefaultCurrency());
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pseA, '50');
        $this->signInAs($this->customer($group));
        $cart = $this->factory->cart();

        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $this->sofa->getId(), (int) $pseA->getId()));

        try {
            $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $this->sofa->getId(), (int) $pseC->getId()));
            self::fail('A sale element the exclusive list does not price must not enter the cart.');
        } catch (ProductNotAvailableException) {
        }

        self::assertSame(1, $this->lineCount($cart));
    }

    /**
     * the exclusive list is in euros and holds A and B; in dollars the catalog holds A, B and C. The
     * buyer who switches to dollars still buys A and B (at the catalog price in dollars), and not C.
     */
    public function testAnExclusiveListStillClosesWhatItDoesNotHoldWhenTheBuyerSwitchesCurrency(): void
    {
        $dollar = $this->factory->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 1.1]);
        $pseA = $this->sofa->getDefaultSaleElements();
        $pseC = $this->factory->productSaleElement($this->sofa);
        $this->factory->productPrice($pseA, $dollar);
        $this->factory->productPrice($pseC, $dollar);
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL-EUR', Currency::getDefaultCurrency(), exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pseA, '50');
        $this->signInAs($this->customer($group));
        $cart = $this->factory->cart(null, ['currency' => $dollar]);

        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $this->sofa->getId(), (int) $pseA->getId()));

        try {
            $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $this->sofa->getId(), (int) $pseC->getId()));
            self::fail('A sale element the exclusive list does not hold must not enter a cart in another currency either.');
        } catch (ProductNotAvailableException) {
        }

        self::assertSame(1, $this->lineCount($cart));
    }

    /**
     * The exclusive list holds A from ten units up: below that, A is not closed, it is bought at the
     * catalog price, and the list prices it from the tier.
     */
    public function testAnExclusiveListThatHoldsASaleElementFromATierUpLetsItBeAddedBelowTheTier(): void
    {
        $pseA = $this->sofa->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL-TIER', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pseA, '9', 10);
        $this->signInAs($this->customer($group));
        $cart = $this->factory->cart();

        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $this->sofa->getId(), (int) $pseA->getId()));

        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();
        self::assertNotNull($line);
        self::assertSame('100.000000', $line->getPrice(), 'the catalog price below the tier');
    }

    /**
     * A fractional quantity (a sale unit sold by the half): the guard and the price of the line read the same
     * whole quantity, so a line the exclusive list holds from ten units up is neither refused nor taken out.
     */
    public function testAFractionalQuantityBelowTheTierOfAnExclusiveListNeitherRefusesNorRemovesTheLine(): void
    {
        $pseA = $this->sofa->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL-HALF', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pseA, '9', 10);
        $member = $this->customer($group);
        $this->signInAs($member);
        $cart = $this->factory->cart();
        $line = $this->factory->cartItem($cart, $this->sofa, $pseA, ['quantity' => 9.5, 'price' => '100.000000', 'promoPrice' => '100.000000']);
        $visibility = $this->getService(CartLineVisibility::class);

        self::assertFalse($visibility->isLineHidden($line));
        self::assertSame([], $visibility->hiddenLinesOf($cart));
        self::assertSame([], $this->getService(CartLinePricer::class)->staleLinesOf($cart, Currency::getDefaultCurrency(), $member), 'priced at the catalog, as it is written');
    }

    public function testALineWhoseProductStoppedBeingForSaleIsJudgedHidden(): void
    {
        $cart = $this->factory->cart();
        $line = $this->factory->cartItem($cart, $this->sofa, null, ['quantity' => 2.0]);
        $this->productShows($this->sofa, PriceDisplayMode::OnRequest);

        self::assertTrue($this->getService(CartLineVisibility::class)->isLineHidden($line));
    }

    public function testAHiddenPriceTakesTheLineOutAndTheSentenceDoesNotNameTheRule(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->sofa);
        $this->factory->cartItem($cart, $this->table);
        $this->signInAs($this->customer());
        $this->productShows($this->sofa, PriceDisplayMode::OnRequest);

        $removed = $this->getService(CartLineVisibility::class)->removeHiddenLines($cart);

        self::assertSame([(int) $this->sofa->getId()], $removed);
        self::assertSame(1, $this->lineCount($cart));
        $sentences = $this->session()->getFlashBag()->get('error');
        self::assertCount(1, $sentences);
        self::assertStringNotContainsStringIgnoringCase('on request', $sentences[0]);
        self::assertStringNotContainsStringIgnoringCase('price', $sentences[0]);
    }

    public function testACartFoundAgainByItsCookieLosesTheLineWhoseModeChanged(): void
    {
        ConfigQuery::write('cart.use_persistent_cookie', '1');
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->sofa);
        $this->factory->cartItem($cart, $this->table);
        $this->getService(RequestStack::class)->getMainRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $cart->getToken());
        $this->session()->setCustomerUser($this->customer());
        $this->productShows($this->sofa, PriceDisplayMode::OnRequest);

        $event = new CartRestoreEvent();
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        $lines = CartItemQuery::create()->filterByCartId($event->getCart()->getId())->find();
        self::assertCount(1, $lines);
        self::assertSame((int) $this->table->getId(), (int) $lines->getFirst()->getProductId());
    }

    public function testTheCheckoutRefusesALineThatIsNotForSaleAndNamesTheProduct(): void
    {
        $cart = $this->factory->cart();
        $line = $this->factory->cartItem($cart, $this->sofa);
        $this->factory->cartItem($cart, $this->table);
        $this->signInAs($this->customer());
        $this->productShows($this->sofa, PriceDisplayMode::OnRequest);

        try {
            $this->getService(CartGuard::class)->checkLinesAreAvailable($cart);
            self::fail('The checkout must refuse a cart holding a product that is not for sale to its buyer.');
        } catch (CartLineUnavailableException $refusal) {
            self::assertSame([(int) $this->sofa->getId()], $refusal->violationDetails()['productIds']);
            self::assertSame([(int) $line->getId()], $refusal->violationDetails()['cartItemIds']);
        }
    }

    public function testAVisitorWhoSignsInKeepsTheLinesTheyMayNowBuy(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->sofa);
        $this->session()->setSessionCart($cart);

        $this->forgetPriceDisplayMemos();
        $this->dispatch(new CustomerLoginEvent($this->customer()), TheliaEvents::CUSTOMER_LOGIN);

        self::assertSame(1, $this->lineCount($cart));
        self::assertSame([], $this->session()->getFlashBag()->peek('error'));
    }

    public function testAShopThatHidesNothingAsksNothingMoreOfTheCart(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->sofa);
        $this->factory->cartItem($cart, $this->table);
        $visibility = $this->getService(CartLineVisibility::class);
        $visibility->hiddenLinesOf($cart);
        $pse = (int) $this->sofa->getDefaultSaleElements()->getId();

        $statements = $this->recordSqlQueries(static function () use ($visibility, $cart, $pse): void {
            self::assertSame([], $visibility->hiddenLinesOf($cart));
            self::assertFalse($visibility->isSaleElementHidden($pse));
        });

        self::assertSame([], $statements);
    }

    private function assertRefused(Cart $cart, Product $product): void
    {
        try {
            $this->add($cart, $product);
            self::fail('A product the buyer is not shown the price of must not enter the cart.');
        } catch (ProductNotAvailableException $refusal) {
            self::assertSame('This product is not available.', $refusal->getMessage());
        }
    }

    private function add(Cart $cart, Product $product): void
    {
        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            $cart,
            (int) $product->getId(),
            (int) $product->getDefaultSaleElements()->getId(),
        ));
    }

    private function lineCount(Cart $cart): int
    {
        return CartItemQuery::create()->filterByCartId($cart->getId())->count();
    }

    private function customer(?CustomerGroup $group = null): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), null === $group ? [] : ['customerGroup' => $group]);
    }

    private function signInAs(Customer $customer): void
    {
        $this->session()->setCustomerUser($customer);
        $this->forgetPriceDisplayMemos();
    }

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
