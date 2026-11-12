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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Currency;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;
use Thelia\Tests\Integration\Domain\Pricing\PriceVisibility\PriceDisplayFixtures;

/**
 * A sale element the caller is not shown the price of, or that an exclusive price list does not
 * price, is refused to the cart by the call itself: the button hidden by the theme is not what
 * keeps it out. The product is found (it is visible: the refusal is a 422, never a 404), and the
 * cart is left as it was.
 */
final class PriceDisplayCartApiTest extends ApiTestCase
{
    use PriceDisplayFixtures;
    use PriceListFixtures;

    private FixtureFactory $factory;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), Currency::getDefaultCurrency(), ['basePrice' => 40.0, 'baseQuantity' => 100]);
        $this->forgetPriceDisplayMemos();
    }

    protected function tearDown(): void
    {
        $this->forgetPriceDisplayMemos();

        parent::tearDown();
    }

    public function testACustomerCannotAddAProductOnRequestByADirectCall(): void
    {
        $this->productShows($this->product, PriceDisplayMode::OnRequest);
        [$token, $cart] = $this->buyer();

        $response = $this->add($token, $cart);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        self::assertSame(Response::HTTP_OK, $this->jsonRequest('GET', '/api/front/products/'.$this->product->getId(), token: $token)->getStatusCode(), 'the product itself is found');
    }

    public function testACustomerAddsAProductThatIsShownToIdentifiedCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        [$token, $cart] = $this->buyer();

        $response = $this->add($token, $cart);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testASaleElementAnExclusiveListDoesNotPriceCannotBeAddedByADirectCall(): void
    {
        $other = $this->factory->productSaleElement($this->product);
        $this->factory->productPrice($other, Currency::getDefaultCurrency(), ['price' => '25.000000', 'promoPrice' => '25.000000']);
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $other, '20');
        $this->forgetPriceDisplayMemos();
        [$token, $cart] = $this->buyer($group);

        $refused = $this->add($token, $cart);
        $accepted = $this->add($token, $cart, $other->getId());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refused->getStatusCode(), (string) $refused->getContent());
        self::assertSame(Response::HTTP_CREATED, $accepted->getStatusCode(), (string) $accepted->getContent());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheQuantityOfALineThatStoppedBeingForSaleCannotBeRaised(): void
    {
        [$token, $cart] = $this->buyer();
        $line = $this->factory->cartItem($cart, $this->product, null, ['quantity' => 2.0]);
        $this->productShows($this->product, PriceDisplayMode::OnRequest);

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => 5], $token);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * The sale element of a cart line is read without its prices: whether the caller may buy it is
     * still the exclusive list's to say, not a default of "yes".
     */
    public function testTheSaleElementOfACartLineIsPurchasableOnlyWhereNoExclusiveListClosesIt(): void
    {
        $open = $this->product->getDefaultSaleElements();
        $closed = $this->factory->productSaleElement($this->product);
        $this->factory->productPrice($closed, Currency::getDefaultCurrency());
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL-LINE', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $open, '20');
        $this->forgetPriceDisplayMemos();
        [$token, $cart] = $this->buyer($group);
        $openLine = $this->factory->cartItem($cart, $this->product, $open);
        $closedLine = $this->factory->cartItem($cart, $this->product, $closed);

        $read = function (int $lineId) use ($token): array {
            $response = $this->jsonRequest('GET', '/api/front/cart_items/'.$lineId, token: $token);
            self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

            return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        };

        $openPayload = $read((int) $openLine->getId());
        $closedPayload = $read((int) $closedLine->getId());

        self::assertTrue($openPayload['productSaleElements']['purchasable'] ?? null);
        self::assertFalse($closedPayload['productSaleElements']['purchasable'] ?? null);
    }

    /**
     * @return array{string, Cart}
     */
    private function buyer(?CustomerGroup $group = null): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => 'password'] + (null === $group ? [] : ['customerGroup' => $group]));

        return [$this->authenticateAsCustomer($customer), $this->factory->cart($customer)];
    }

    private function add(string $token, Cart $cart, ?int $saleElementId = null): Response
    {
        return $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.($saleElementId ?? $this->product->getDefaultSaleElements()->getId()),
            'quantity' => 2,
        ], $token);
    }
}
