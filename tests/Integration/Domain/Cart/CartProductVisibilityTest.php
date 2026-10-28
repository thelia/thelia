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

namespace Thelia\Tests\Integration\Domain\Cart;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\Exception\CartLineUnavailableException;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Cart\Exception\ProductNotAvailableException;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Cart\Service\CartLineVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Checkout\DTO\CheckoutViolation;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\Step\CartStepProvider;
use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Category;
use Thelia\Model\CheckoutStep;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The cart holds what the catalog shows its visitor, whichever way a line gets in: an addition
 * refuses a product the group of the visitor does not see, a sign-in takes the lines it does
 * not see out of the cart it brings along, and the checkout refuses one that is still there.
 *
 * The example: the professionals are restricted to Sofas. A visitor (default group, nothing
 * closed) puts a dining table (Dining Room) and a sofa (Sofas) in their cart and signs in as a
 * professional: the cart holds the sofa, and the visitor is told about the table.
 */
final class CartProductVisibilityTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    private CustomerGroup $pro;

    private Product $table;

    private Product $sofa;

    private Category $diningRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diningRoom = $this->factory->category();
        $sofas = $this->factory->category();
        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();
        $this->table = $this->factory->product($this->diningRoom, $taxRule, $currency, ['baseQuantity' => 10]);
        $this->sofa = $this->factory->product($sofas, $taxRule, $currency, ['baseQuantity' => 10]);
        $this->pro = $this->factory->customerGroup('pro', categoryIds: [(int) $sofas->getId()]);
        $this->forgetResolved();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->session()->setSessionCart(null);
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->remove(ConfigQuery::read('cart.cookie_name', 'thelia_cart'));
        ConfigQuery::resetCache();
        $this->forgetResolved();

        parent::tearDown();
    }

    public function testAProductOfAClosedCategoryIsRefusedAndTheCartIsLeftAsItWas(): void
    {
        $this->signInAs($this->professional());
        $cart = $this->factory->cart();

        try {
            $this->add($cart, $this->table);
            self::fail('A product the group does not see must not enter the cart.');
        } catch (ProductNotAvailableException $refusal) {
            self::assertInstanceOf(InvalidCartException::class, $refusal, 'Every caller answering a refusal of the cart answers this one.');
            self::assertSame('This product is not available.', $refusal->getMessage());
        }

        self::assertSame(0, $this->lineCount($cart));
    }

    public function testAProductOfAnOpenCategoryIsAdded(): void
    {
        $this->signInAs($this->professional());
        $cart = $this->factory->cart();

        $this->add($cart, $this->sofa);

        self::assertSame(1, $this->lineCount($cart));
    }

    public function testTheDefaultGroupAddsEverything(): void
    {
        $this->signInAs($this->consumer());
        $cart = $this->factory->cart();

        $this->add($cart, $this->table);
        $this->add($cart, $this->sofa);

        self::assertSame(2, $this->lineCount($cart));
    }

    public function testAForgedFormRefersTheSaleElementOfTheClosedProductUnderTheProductOfAnOpenOne(): void
    {
        // What the live component of the product page takes from `formValues`: the product of
        // a page the visitor may see, the sale element of one they may not.
        $this->signInAs($this->professional());
        $cart = $this->factory->cart();

        $this->expectException(ProductNotAvailableException::class);

        try {
            $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
                $cart,
                (int) $this->sofa->getId(),
                (int) $this->table->getDefaultSaleElements()->getId(),
            ));
        } finally {
            self::assertSame(0, $this->lineCount($cart));
        }
    }

    public function testAModuleDispatchingTheEventItselfIsRefusedToo(): void
    {
        $this->signInAs($this->professional());
        $cart = $this->factory->cart();
        $event = (new CartEvent($cart))
            ->setProductId((int) $this->table->getId())
            ->setProductSaleElementsId((int) $this->table->getDefaultSaleElements()->getId())
            ->setQuantity(1)
            ->setNewness(true)
            ->setAppend(true);

        $this->expectException(ProductNotAvailableException::class);

        try {
            $this->dispatch($event, TheliaEvents::CART_ADDITEM);
        } finally {
            self::assertSame(0, $this->lineCount($cart));
        }
    }

    public function testASaleElementThatDoesNotExistIsLeftToTheAdditionToAnswer(): void
    {
        $this->signInAs($this->professional());

        self::assertFalse($this->getService(CartLineVisibility::class)->isSaleElementHidden(2_000_000_000));
    }

    public function testSigningInAsAProfessionalTakesTheClosedLinesOutOfTheCartAndSaysSo(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $sofaLine = $this->factory->cartItem($cart, $this->sofa);
        $this->session()->setSessionCart($cart);
        $professional = $this->professional();

        $this->signIn($professional);

        $lines = CartItemQuery::create()->filterByCartId($cart->getId())->find();
        self::assertCount(1, $lines);
        self::assertSame((int) $sofaLine->getId(), (int) $lines->getFirst()->getId());
        self::assertSame((int) $professional->getId(), (int) CartQuery::create()->findPk($cart->getId())?->getCustomerId(), 'The cart is the customer\'s.');
        $notices = $this->session()->getFlashBag()->peek('error');
        self::assertCount(1, $notices);
        self::assertStringContainsString($this->titleOf($this->table), $notices[0]);
        self::assertStringNotContainsString($this->titleOf($this->sofa), $notices[0]);
    }

    public function testSigningInAsAConsumerKeepsTheWholeCartAndSaysNothing(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $this->factory->cartItem($cart, $this->sofa);
        $this->session()->setSessionCart($cart);

        $this->signIn($this->consumer());

        self::assertSame(2, $this->lineCount($cart));
        self::assertSame([], $this->session()->getFlashBag()->peek('error'));
    }

    public function testAnOfferedLineIsTheShopsAndStaysInTheCart(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table, null, ['isOffered' => 1, 'offeredByCouponId' => 1]);
        $this->signInAs($this->professional());

        self::assertSame([], $this->getService(CartLineVisibility::class)->removeHiddenLines($cart));
        self::assertSame(1, $this->lineCount($cart));
        $this->assertCartPassesTheGuard($cart);
    }

    public function testACartFoundAgainByItsCookieLosesTheLinesTheCustomerDoesNotSee(): void
    {
        ConfigQuery::write('cart.use_persistent_cookie', '1');
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $this->factory->cartItem($cart, $this->sofa);
        $this->getService(RequestStack::class)->getMainRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $cart->getToken());
        $this->session()->setCustomerUser($this->professional());
        $this->forgetResolved();

        $event = new CartRestoreEvent();
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        $lines = CartItemQuery::create()->filterByCartId($event->getCart()->getId())->find();
        self::assertCount(1, $lines);
        self::assertSame((int) $this->sofa->getId(), (int) $lines->getFirst()->getProductId());
    }

    public function testNoSessionIsStartedToTellTheVisitorWhatWasTakenOut(): void
    {
        // A request whose session is only a factory (a stateless call, a worker): the lines still
        // go out of the cart, and the sentence is not worth starting a session for.
        $started = 0;
        $request = new Request();
        $request->setSessionFactory(function () use (&$started): Session {
            ++$started;

            return $this->session();
        });
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $visibility = new CartLineVisibility(
            $this->getService(ProductVisibility::class),
            $requestStack,
            $this->getService(CartShippingRules::class),
        );
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $this->signInAs($this->professional());

        self::assertSame([(int) $this->table->getId()], $visibility->removeHiddenLines($cart));

        self::assertSame(0, $started);
        self::assertSame(0, $this->lineCount($cart));
    }

    public function testTheCheckoutRefusesALineTheCustomerDoesNotSeeAndNamesTheProduct(): void
    {
        $cart = $this->factory->cart();
        $line = $this->factory->cartItem($cart, $this->table);
        $this->factory->cartItem($cart, $this->sofa);
        $this->signInAs($this->professional());

        try {
            $this->getService(CartGuard::class)->checkLinesAreAvailable($cart);
            self::fail('The checkout must refuse a cart holding a product the customer does not see.');
        } catch (CartLineUnavailableException $refusal) {
            self::assertSame(CheckoutViolationCode::CartLineUnavailable->value, $refusal->violationCode());
            self::assertSame([(int) $this->table->getId()], $refusal->violationDetails()['productIds']);
            self::assertSame([(int) $line->getId()], $refusal->violationDetails()['cartItemIds']);
            self::assertStringContainsString($this->titleOf($this->table), $refusal->getMessage());
        }
    }

    public function testTheCartStepOfTheCheckoutIsTheOneThatRefusesIt(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $this->signInAs($this->professional());

        $this->expectException(CartLineUnavailableException::class);

        $this->getService(CartStepProvider::class)->check($cart);
    }

    public function testThePlacementOfTheFrontApiReportsItWithAStableCodeOnTheCartStep(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $this->signInAs($this->professional());

        $violations = array_values(array_filter(
            $this->getService(CheckoutValidationService::class)->collectViolations($cart),
            static fn (CheckoutViolation $violation): bool => CheckoutViolationCode::CartLineUnavailable->value === $violation->code,
        ));

        self::assertCount(1, $violations);
        self::assertSame(CheckoutStep::CODE_CART, $violations[0]->stepCode);
        self::assertSame('cart-line-unavailable', $violations[0]->code);
    }

    public function testAQuantityIsNotRaisedOnALineTheCustomerNoLongerSeesButItMayBeReducedOrRemoved(): void
    {
        $cart = $this->factory->cart();
        $line = $this->factory->cartItem($cart, $this->table, null, ['quantity' => 3.0]);
        $this->signInAs($this->professional());

        try {
            $this->changeQuantity($cart, $line, 5);
            self::fail('The quantity of a line the customer does not see must not grow.');
        } catch (ProductNotAvailableException) {
            self::assertSame(3.0, $this->quantityOf($line), 'The line is left as it was.');
        }

        $this->changeQuantity($cart, $line, 2);
        self::assertSame(2.0, $this->quantityOf($line), 'A reduction is a way out of the cart: it is allowed.');

        $this->dispatch((new CartEvent($cart))->setCartItemId((int) $line->getId()), TheliaEvents::CART_DELETEITEM);
        self::assertSame(0, $this->lineCount($cart), 'A removal is allowed too.');
    }

    public function testAQuantityIsRaisedOnALineTheCustomerSees(): void
    {
        $cart = $this->factory->cart();
        $line = $this->factory->cartItem($cart, $this->sofa);
        $this->signInAs($this->professional());

        $this->changeQuantity($cart, $line, 4);

        self::assertSame(4.0, $this->quantityOf($line));
    }

    public function testALineIsJudgedOnTheProductOfItsSaleElementWhateverProductIdItCarries(): void
    {
        $cart = $this->factory->cart();
        $tableLine = $this->factory->cartItem($cart, $this->table);
        $sofaLine = $this->factory->cartItem($cart, $this->sofa);
        // Written the way a module or an old import could: the product id of each line names the
        // other product. The sale element is what the cart sells.
        CartItemQuery::create()->filterById($tableLine->getId())->update(['ProductId' => (int) $this->sofa->getId()]);
        CartItemQuery::create()->filterById($sofaLine->getId())->update(['ProductId' => (int) $this->table->getId()]);
        CartItemTableMap::clearInstancePool();
        $this->signInAs($this->professional());

        $hidden = $this->getService(CartLineVisibility::class)->hiddenLinesOf($cart);

        self::assertSame([(int) $tableLine->getId()], array_map(static fn ($line): int => (int) $line->getId(), $hidden));
    }

    public function testASaleElementNoPriceReachesIsRefusedWithAnExceptionAModuleCanCatch(): void
    {
        $this->signInAs($this->consumer());
        $cart = $this->factory->cart();
        $productSaleElementsId = (int) $this->sofa->getDefaultSaleElements()->getId();
        ProductPriceQuery::create()->filterByProductSaleElementsId($productSaleElementsId)->delete();
        ProductPriceTableMap::clearInstancePool();
        $event = (new CartEvent($cart))
            ->setProductId((int) $this->sofa->getId())
            ->setProductSaleElementsId($productSaleElementsId)
            ->setQuantity(1)
            ->setNewness(true)
            ->setAppend(true);

        try {
            $this->dispatch($event, TheliaEvents::CART_ADDITEM);
            self::fail('A sale element nothing prices has no line: the addition must say so.');
        } catch (InvalidCartException $refusal) {
            self::assertSame('This product has no price: it cannot be added to the cart.', $refusal->getMessage());
        }

        self::assertSame(0, $this->lineCount($cart));
        self::assertNull($event->getCartItem());
    }

    public function testPayingDirectlyWithALineTheCustomerDoesNotSeeIsRefusedBeforeAnythingIsWritten(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $this->signInAs($this->professional());

        try {
            $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest($cart, 1, 1, 1, 1));
            self::fail('A caller that pays without validating first must not place an order for a hidden product.');
        } catch (CartLineUnavailableException $refusal) {
            self::assertSame([(int) $this->table->getId()], $refusal->violationDetails()['productIds']);
        }
    }

    public function testTheCheckoutAcceptsTheSameCartForTheDefaultGroup(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $this->factory->cartItem($cart, $this->sofa);
        $this->signInAs($this->consumer());

        $this->assertCartPassesTheGuard($cart);
        $this->getService(CartStepProvider::class)->check($cart);
    }

    public function testAWholeCartIsJudgedInOneQueryWhateverItsSize(): void
    {
        $this->signInAs($this->professional());
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $this->factory->cartItem($cart, $this->sofa);
        $visibility = $this->getService(CartLineVisibility::class);

        $statementsOfTwoLines = $this->recordSqlQueries(static fn () => $visibility->hiddenLinesOf($cart));

        foreach ([1, 2] as $ignored) {
            $this->factory->cartItem($cart, $this->factory->product($this->diningRoom, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]));
        }

        $statementsOfFourLines = $this->recordSqlQueries(static fn () => $visibility->hiddenLinesOf($cart));

        self::assertCount(3, $visibility->hiddenLinesOf($cart));
        // The first call also reads what a request reads once (the lists of the groups, the tree of
        // the categories): the lines themselves cost the one statement, and no more with more of them.
        $readsOfTheLines = static fn (array $statements): int => \count(array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'FROM `cart_item`')));
        self::assertSame(1, $readsOfTheLines($statementsOfTwoLines));
        self::assertSame(1, $readsOfTheLines($statementsOfFourLines));
        self::assertCount(1, $statementsOfFourLines);
    }

    public function testNothingIsAskedWhileNoGroupIsRestricted(): void
    {
        $this->pro->setRestrictsCategories(0)->save();
        $this->forgetResolved();
        $this->signInAs($this->professional());
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->table);
        $visibility = $this->getService(CartLineVisibility::class);

        $statements = $this->recordSqlQueries(static function () use ($visibility, $cart): void {
            $visibility->hiddenLinesOf($cart);
            $visibility->isSaleElementHidden(1);
        });

        self::assertSame([], array_values(array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'cart_item') || str_contains($sql, 'product_sale_elements'))));
    }

    private function add(Cart $cart, Product $product): void
    {
        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            $cart,
            (int) $product->getId(),
            (int) $product->getDefaultSaleElements()->getId(),
        ));
    }

    private function assertCartPassesTheGuard(Cart $cart): void
    {
        $this->getService(CartGuard::class)->checkLinesAreAvailable($cart);
        $this->addToAssertionCount(1);
    }

    private function changeQuantity(Cart $cart, CartItem $line, int $quantity): void
    {
        $this->dispatch(
            (new CartEvent($cart))->setCartItemId((int) $line->getId())->setQuantity($quantity),
            TheliaEvents::CART_UPDATEITEM,
        );
    }

    private function quantityOf(CartItem $line): float
    {
        CartItemTableMap::clearInstancePool();

        return (float) CartItemQuery::create()->findPk($line->getId())?->getQuantity();
    }

    private function lineCount(Cart $cart): int
    {
        return CartItemQuery::create()->filterByCartId($cart->getId())->count();
    }

    private function titleOf(Product $product): string
    {
        // The fixtures give no title: the reference is what a message names a product by then.
        $title = (string) ProductI18nQuery::create()->filterById($product->getId())->filterByLocale('en_US')->findOne()?->getTitle();

        return '' !== $title ? $title : (string) $product->getRef();
    }

    private function professional(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $this->pro]);
    }

    private function consumer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * The customer as the catalog reads them, without the login events.
     */
    private function signInAs(Customer $customer): void
    {
        $this->session()->setCustomerUser($customer);
        $this->forgetResolved();
    }

    /**
     * The customer signing in: the login event of the form, as the theme and the API dispatch it.
     */
    private function signIn(Customer $customer): void
    {
        $this->forgetResolved();
        $this->dispatch(new CustomerLoginEvent($customer), TheliaEvents::CUSTOMER_LOGIN);
    }

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }

    private function forgetResolved(): void
    {
        $this->getService('services_resetter')->reset();
    }
}
