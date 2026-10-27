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

namespace Thelia\Tests\Integration\Action;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Action\Cart as CartAction;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Cart\Service\CartLinePricer;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The line of a cart is priced by the customer price context, whoever writes it:
 * the discount of the group, its own price, the discount of the customer, in one
 * resolution for the whole cart.
 */
final class CartCustomerPriceContextTest extends ActionIntegrationTestCase
{
    use ForgetsPooledModels;
    use RecordsSqlQueries;

    private const CATALOG_PRICE = 100.0;

    private Currency $currency;

    private CustomerGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->factory->currency();
        $this->group = $this->factory->customerGroup('pro', 15.0);
    }

    public function testAGuestPaysTheCatalogPrice(): void
    {
        $line = $this->addToCart($this->factory->cart(), $this->product());

        self::assertSame(self::CATALOG_PRICE, $line->getRealPrice());
        self::assertSame(0, (int) $line->getPromo());
    }

    public function testAMemberOfAGroupPaysTheCatalogLessTheDiscountOfTheGroup(): void
    {
        $line = $this->addToCart($this->factory->cart($this->member()), $this->product());

        self::assertSame(85.0, $line->getRealPrice());
        self::assertSame(0, (int) $line->getPromo());
    }

    public function testAMemberPaysThePriceTheGroupHasOfItsOwn(): void
    {
        $product = $this->product();
        $this->factory->customerGroupProductPrice($this->group, $this->pseOf($product), $this->currency, 42.0);

        $line = $this->addToCart($this->factory->cart($this->member()), $product);

        self::assertSame(42.0, $line->getRealPrice());
        self::assertSame(0, (int) $line->getPromo(), 'A price of the group is not a promotion.');
    }

    public function testTheDiscountOfTheCustomerComesAfterTheOneOfTheGroup(): void
    {
        $customer = $this->member();
        $customer->setDiscount('10.000000')->save();

        $line = $this->addToCart($this->factory->cart($customer), $this->product());

        // 100, less 15 % for the group, less 10 % for the customer.
        self::assertSame(76.5, $line->getRealPrice());
    }

    public function testTheDiscountOfTheCustomerAppliesToThePriceOfTheGroup(): void
    {
        $customer = $this->member();
        $customer->setDiscount('10.000000')->save();
        $product = $this->product();
        $this->factory->customerGroupProductPrice($this->group, $this->pseOf($product), $this->currency, 42.0);

        $line = $this->addToCart($this->factory->cart($customer), $product);

        self::assertEqualsWithDelta(37.8, $line->getRealPrice(), 0.0000001);
    }

    public function testTheLineIsWrittenRoundedToSixDecimals(): void
    {
        $customer = $this->member();
        $customer->setDiscount('10.000000')->save();
        $product = $this->product(100 / 3);

        $line = $this->addToCart($this->factory->cart($customer), $product);

        // 33.333333 (the column keeps six decimals) x 0.85 x 0.9 = 25.49999974...
        self::assertSame('25.500000', $line->getPrice());
        self::assertSame('25.500000', $line->getPromoPrice());
    }

    public function testASaleElementNothingPricesIsNotAddedToTheCart(): void
    {
        $product = $this->product();
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->pseOf($product)->getId())->delete();
        $cart = $this->factory->cart();

        try {
            $this->dispatchAddItem($cart, $product);
            self::fail('A sale element nothing prices has no line: a module dispatching the event must be told, not handed an event with no line.');
        } catch (InvalidCartException $refusal) {
            self::assertSame('This product has no price: it cannot be added to the cart.', $refusal->getMessage());
        }

        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testChangingTheCurrencyRepricesTheLinesInTheNewCurrency(): void
    {
        $customer = $this->member();
        $cart = $this->factory->cart($customer);
        $line = $this->addToCart($cart, $this->product());
        self::assertSame(85.0, $line->getRealPrice());

        $other = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0 * (float) Currency::getDefaultCurrency()->getRate()]);

        $this->forgetResolvedPrices();
        $cart->reload();
        $this->getService(CartAction::class)->updateCartPrices($cart, $other);

        $line->reload();
        self::assertSame(170.0, $line->getRealPrice(), '100 x 2, less 15 %.');
        self::assertSame($other->getId(), $cart->getCurrencyId());
    }

    public function testASaleElementNothingPricesAnymoreKeepsTheOldPriceOfItsLine(): void
    {
        $customer = $this->member();
        $cart = $this->factory->cart($customer);
        $product = $this->product();
        $line = $this->addToCart($cart, $product);
        self::assertSame(85.0, $line->getRealPrice());

        ProductPriceQuery::create()->filterByProductSaleElementsId($this->pseOf($product)->getId())->delete();
        $this->forgetResolvedPrices();
        $cart->reload();
        $this->getService(CartAction::class)->updateCartPrices($cart, $this->currency);

        $line->reload();
        self::assertSame(85.0, $line->getRealPrice());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testARefreshThatChangesNothingWritesNothing(): void
    {
        $customer = $this->member();
        $customer->setDiscount('10.000000')->save();
        $cart = $this->factory->cart($customer);

        // A price that is not round once the two discounts are applied: the stored
        // line holds six decimals, the context resolves more.
        foreach ([100 / 3, 19.99, 7.77] as $price) {
            $this->addToCart($cart, $this->product($price));
        }

        $statements = $this->refreshAndRecord($cart);

        self::assertCount(1, $this->statementsStarting($statements, 'SELECT', 'FROM `cart_item`'), 'Control: the lines were read.');
        self::assertSame([], $this->statementsStarting($statements, 'UPDATE', '`cart_item`'));
    }

    public function testALineAlreadyAtItsPriceIsNotWrittenAgain(): void
    {
        $customer = $this->member();
        $customer->setDiscount('10.000000')->save();
        $line = $this->addToCart($this->factory->cart($customer), $this->product(100 / 3));
        $pricer = $this->getService(CartLinePricer::class);
        $cart = $line->getCart();

        // 25.49999974... is what the context resolves, 25.500000 what the line holds.
        self::assertSame([], $pricer->price([$line], $this->currency, $cart->getCustomer()));
    }

    public function testTheNumberOfStatementsOfARefreshDoesNotGrowWithTheLines(): void
    {
        $customer = $this->member();
        $customer->setDiscount('10.000000')->save();
        $products = [];

        for ($i = 0; $i < 20; ++$i) {
            $products[] = $this->product(10.0 + $i);
        }

        $small = $this->factory->cart($customer);
        $large = $this->factory->cart($customer);

        foreach (\array_slice($products, 0, 2) as $product) {
            $this->addToCart($small, $product);
        }

        foreach ($products as $product) {
            $this->addToCart($large, $product);
        }

        $reads = fn (Cart $cart): int => \count($this->statementsStarting($this->refreshAndRecord($cart), 'SELECT'));

        // What is read once for the whole process is read here.
        $reads($small);
        $reads($large);

        self::assertSame($reads($small), $reads($large), 'The same reads for 2 lines and for 20.');
    }

    public function testARefreshWritesEveryLineThatDisagreesWithItsPrice(): void
    {
        $customer = $this->member();
        $cart = $this->factory->cart($customer);

        foreach ([10.0, 20.0, 30.0] as $price) {
            $this->addToCart($cart, $this->product($price));
        }

        CartItemQuery::create()->filterByCartId($cart->getId())->update(['Price' => '1.000000', 'PromoPrice' => '1.000000']);

        $statements = $this->refreshAndRecord($cart);

        self::assertCount(3, $this->statementsStarting($statements, 'UPDATE', '`cart_item`'));
        self::assertSame(
            ['8.500000', '17.000000', '25.500000'],
            array_map(
                static fn (CartItem $line): string => (string) $line->getPrice(),
                iterator_to_array(CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find(), false),
            ),
        );
    }

    public function testASignedInCustomerSettlesTheLineWhenTheCartChanges(): void
    {
        $customer = $this->member();
        $cart = $this->factory->cart($customer);
        $line = $this->addToCart($cart, $this->product());
        // The group got a better rate while the cart sat open.
        $cart->getCustomer()->getCustomerGroup()->setDiscount('30.000000')->save();
        $this->forgetResolvedPrices();

        $event = new CartEvent($cart);
        $event->setCartItemId($line->getId())->setQuantity(2);
        $this->dispatch($event, TheliaEvents::CART_UPDATEITEM);

        $line->reload();
        self::assertSame(70.0, $line->getRealPrice());
    }

    public function testAGuestCartTakesThePriceOfTheGroupOfTheCustomerWhoSignsIn(): void
    {
        $product = $this->product();
        $cart = $this->factory->cart();
        $this->addToCart($cart, $product);
        $customer = $this->member();

        $restored = $this->restoreCartAs($cart, $customer);

        self::assertNotSame($cart->getId(), $restored->getId(), 'The cart is repriced, so it is copied.');
        self::assertSame(85.0, $this->onlyLineOf($restored)->getRealPrice());
    }

    public function testAGuestCartTakesThePriceTheGroupHasOfItsOwnWhenTheCustomerSignsIn(): void
    {
        $product = $this->product();
        $this->factory->customerGroupProductPrice($this->group, $this->pseOf($product), $this->currency, 42.0);
        $cart = $this->factory->cart();
        $this->addToCart($cart, $product);

        $restored = $this->restoreCartAs($cart, $this->member());

        self::assertSame(42.0, $this->onlyLineOf($restored)->getRealPrice());
    }

    public function testTheCartDuplicatedAtSignInIsPricedByTheDuplicationItself(): void
    {
        $cart = $this->factory->cart();
        $this->addToCart($cart, $this->product());
        $customer = $this->member();
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        // What follows a duplication (the refresh of a restored cart) is not involved.
        $copy = (new \ReflectionMethod(CartAction::class, 'duplicateCart'))
            ->invoke($this->getService(CartAction::class), $this->dispatcher, $cart, $customer);

        self::assertInstanceOf(Cart::class, $copy);
        self::assertSame(85.0, $this->onlyLineOf($copy)->getRealPrice());
    }

    public function testADuplicatedCartIsPricedForTheCustomerOfTheCopy(): void
    {
        $product = $this->product();
        $cart = $this->factory->cart();
        $this->addToCart($cart, $product);
        $this->factory->customerGroupProductPrice($this->group, $this->pseOf($product), $this->currency, 42.0);
        $this->forgetResolvedPrices();

        $copy = $cart->duplicate('copy-'.uniqid(), $this->member(), $this->currency, $this->dispatcher, null, $this->getService(CartLinePricer::class));

        self::assertInstanceOf(Cart::class, $copy);
        self::assertSame(42.0, $this->onlyLineOf($copy)->getRealPrice());
    }

    public function testADuplicatedCartGivesAnUnpricedSaleElementTheOldPriceOfItsLine(): void
    {
        $product = $this->product();
        $cart = $this->factory->cart();
        $this->addToCart($cart, $product);
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->pseOf($product)->getId())->delete();

        $copy = $cart->duplicate('copy-'.uniqid(), $this->member(), $this->currency, $this->dispatcher, null, $this->getService(CartLinePricer::class));

        self::assertInstanceOf(Cart::class, $copy);
        self::assertSame(self::CATALOG_PRICE, $this->onlyLineOf($copy)->getRealPrice());
    }

    /**
     * A discount outside ]0, 100] (only a direct write to the database can leave one) counts for
     * nothing in the copy as everywhere else: it neither makes the price negative nor is capped.
     */
    #[DataProvider('discountsThatCountForNothing')]
    public function testADuplicatedCartIgnoresADiscountOutsideZeroToOneHundred(string $discount): void
    {
        $product = $this->product();
        $cart = $this->factory->cart();
        $this->addToCart($cart, $product);
        $customer = $this->member();
        $customer->setDiscount($discount)->save($this->getPropelConnection());

        $copy = $cart->duplicate('copy-'.uniqid(), $customer, $this->currency, $this->dispatcher, null, $this->getService(CartLinePricer::class));

        self::assertInstanceOf(Cart::class, $copy);
        // The 15 % of the group of the customer, and no discount of the customer.
        self::assertEqualsWithDelta(85.0, $this->onlyLineOf($copy)->getRealPrice(), 0.0000001);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function discountsThatCountForNothing(): iterable
    {
        yield 'above 100' => ['150.000000'];
        yield 'negative' => ['-20.000000'];
    }

    public function testADuplicatedCartInTheSameCurrencyAppliesTheDiscountOfTheGroup(): void
    {
        $product = $this->product();
        $cart = $this->factory->cart();
        $this->addToCart($cart, $product);

        $copy = $cart->duplicate('copy-'.uniqid(), $this->member(), $this->currency, $this->dispatcher, null, $this->getService(CartLinePricer::class));

        self::assertInstanceOf(Cart::class, $copy);
        self::assertEqualsWithDelta(85.0, $this->onlyLineOf($copy)->getRealPrice(), 0.0000001, 'the group of the customer of the copy applies');
    }

    /**
     * Refreshes the prices of a cart read afresh, as a request that starts with
     * nothing pooled does, and returns what the database was asked.
     *
     * @return list<string>
     */
    private function refreshAndRecord(Cart $cart): array
    {
        $cartId = $cart->getId();
        $this->forgetResolvedPrices();
        $cartAction = $this->getService(CartAction::class);

        return $this->recordSqlQueriesWithoutPooledModels(function () use ($cartAction, $cartId): void {
            $cartAction->updateCartPrices(CartQuery::create()->findPk($cartId) ?? self::fail('The cart is gone.'), $this->currency);
        });
    }

    /**
     * @param list<string> $statements
     *
     * @return list<string> the statements starting with the verb and containing the fragment
     */
    private function statementsStarting(array $statements, string $verb, string $fragment = ''): array
    {
        return array_values(array_filter(
            $statements,
            static fn (string $statement): bool => str_starts_with(ltrim($statement), $verb) && str_contains($statement, $fragment),
        ));
    }

    private function member(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $this->group]);
    }

    private function product(float $price = self::CATALOG_PRICE): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => $price],
        );
    }

    private function pseOf(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne() ?? self::fail('The product has no default sale element.');
    }

    private function dispatchAddItem(Cart $cart, Product $product): CartEvent
    {
        $event = new CartEvent($cart);
        $event
            ->setProductId($product->getId())
            ->setProductSaleElementsId($this->pseOf($product)->getId())
            ->setQuantity(1)
            ->setNewness(true)
            ->setAppend(true);

        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        return $event;
    }

    private function addToCart(Cart $cart, Product $product): CartItem
    {
        return $this->dispatchAddItem($cart, $product)->getCartItem() ?? self::fail('The line was not added.');
    }

    private function onlyLineOf(Cart $cart): CartItem
    {
        $lines = CartItemQuery::create()->filterByCartId($cart->getId())->find();
        self::assertCount(1, $lines);

        return $lines->getFirst();
    }

    private function restoreCartAs(Cart $cart, Customer $customer): Cart
    {
        $this->getService(RequestStack::class)->getMainRequest()->cookies->set(
            ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
            $cart->getToken(),
        );
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
        $this->forgetResolvedPrices();

        $event = new CartRestoreEvent();
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        return $event->getCart();
    }

    /**
     * A new request remembers nothing of what the previous one resolved.
     */
    private function forgetResolvedPrices(): void
    {
        $this->getService(CustomerPriceContext::class)->reset();
        $this->getService(EffectivePriceCatalog::class)->reset();
        $this->getService('services_resetter')->reset();
    }
}
