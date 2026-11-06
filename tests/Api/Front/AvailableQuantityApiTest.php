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

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ApiTestCase;

/**
 * How many units the visitor may still buy of a sale element, as the front API, the data access
 * of the themes and the cart facade tell it: one rule, one number.
 */
final class AvailableQuantityApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::resetCache();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testThePlanExampleServesTheLimitOfThePreorderAsWhatMayBeBought(): void
    {
        $saleElements = $this->saleElements(0, 2, 10.0, '2026-12-01');

        $read = $this->front($saleElements);

        self::assertSame(0, $read['quantity']);
        self::assertSame(2, $read['outOfStockPolicy']);
        self::assertSame(10.0, (float) $read['preorderLimit']);
        self::assertStringStartsWith('2026-12-01', $read['restockDate']);
        self::assertSame(10.0, self::asFloat($read['availableQuantity']));
    }

    /**
     * @return iterable<string, array{0: int, 1: int, 2: ?float, 3: ?float}> stock, mode, limit, expected
     */
    public static function modes(): iterable
    {
        yield 'refuse, in stock' => [3, 0, 10.0, 3.0];
        yield 'refuse, nothing left' => [0, 0, null, 0.0];
        yield 'limited, in stock: stock plus limit' => [3, 2, 10.0, 13.0];
        yield 'limited, limit used up' => [-10, 2, 10.0, 0.0];
        yield 'unlimited: no limit' => [0, 1, null, null];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('modes')]
    public function testEveryReaderTellsTheSameNumber(int $stock, int $mode, ?float $limit, ?float $expected): void
    {
        $saleElements = $this->saleElements($stock, $mode, $limit);

        $api = self::asFloat($this->front($saleElements)['availableQuantity'] ?? null);
        $facade = self::getContainer()->get(CartLineAvailability::class)->forLine($saleElements);
        $themes = self::asFloat($this->psesOf($saleElements->getProduct())[0]['availableQuantity']);

        self::assertSame($expected, $api, 'API');
        self::assertSame($expected, $themes, 'Data access of the themes');
        self::assertSame($expected ?? (float) CartLineAvailability::UNLIMITED, $facade, 'Cart facade');
    }

    public function testNoLimitIsServedWhenTheShopDoesNotCheckTheStock(): void
    {
        ConfigQuery::write('check-available-stock', '0');
        ConfigQuery::resetCache();
        $saleElements = $this->saleElements(0, 0, null);

        self::assertNull($this->front($saleElements)['availableQuantity'] ?? null, 'The API leaves a null out.');
        self::assertNull($this->psesOf($saleElements->getProduct())[0]['availableQuantity']);
    }

    public function testNoLimitIsServedForAVirtualProduct(): void
    {
        $saleElements = $this->saleElements(0, 0, null);
        $saleElements->getProduct()->setVirtual(1)->save();

        self::assertNull($this->front($saleElements)['availableQuantity'] ?? null, 'The API leaves a null out.');
    }

    public function testTheDataAccessOfTheThemesTellsWhatIsOnPreorder(): void
    {
        $preorder = $this->psesOf($this->saleElements(0, 2, 10.0, '2026-12-01')->getProduct())[0];
        $inStock = $this->psesOf($this->saleElements(5, 2, 10.0, '2026-12-01')->getProduct())[0];
        $refused = $this->psesOf($this->saleElements(0, 0, null)->getProduct())[0];
        $unlimited = $this->psesOf($this->saleElements(0, 1, null)->getProduct())[0];
        $usedUp = $this->psesOf($this->saleElements(-10, 2, 10.0)->getProduct())[0];

        self::assertSame(['quantity' => 0, 'availableQuantity' => 10.0, 'outOfStockPolicy' => 2, 'restockDate' => '2026-12-01', 'isPreorder' => true], $this->subset($preorder));
        self::assertFalse($inStock['isPreorder'], 'Stock is there: not a preorder.');
        self::assertFalse($refused['isPreorder']);
        self::assertTrue($unlimited['isPreorder']);
        self::assertFalse($usedUp['isPreorder'], 'The limit is used up: nothing can be bought.');
    }

    public function testTheAdminReadsNoAvailableQuantity(): void
    {
        $saleElements = $this->saleElements(0, 2, 10.0);

        $read = self::decodeJson($this->jsonRequest('GET', '/api/admin/product_sale_elements/'.$saleElements->getId(), token: $this->authenticateAsAdmin()));

        self::assertArrayNotHasKey('availableQuantity', $read);
    }

    public function testTheCartTakesTenOnALimitOfTenAndRefusesTheEleventh(): void
    {
        $saleElements = $this->saleElements(0, 2, 10.0);
        $customer = $this->createFixtureFactory()->customer($this->createFixtureFactory()->customerTitle(), ['password' => 'password']);
        $cart = $this->createFixtureFactory()->cart($customer);
        $token = $this->authenticateAsCustomer($customer);

        $first = $this->addToCart($cart->getId(), $saleElements, 10, $token);
        self::assertJsonResponseSuccessful($first);

        $eleventh = $this->addToCart($cart->getId(), $saleElements, 1, $token);

        self::assertSame(422, $eleventh->getStatusCode(), (string) $eleventh->getContent());
        self::assertSame(10.0, (float) CartItemQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection())->getQuantity());
    }

    public function testTheCartRefusesAnythingAboveTheStockInTheModeThatRefuses(): void
    {
        $saleElements = $this->saleElements(3, 0, 10.0);
        $customer = $this->createFixtureFactory()->customer($this->createFixtureFactory()->customerTitle(), ['password' => 'password']);
        $cart = $this->createFixtureFactory()->cart($customer);

        $response = $this->addToCart($cart->getId(), $saleElements, 4, $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    private function saleElements(int $quantity, int $mode, ?float $limit, ?string $restockDate = null): ProductSaleElements
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => $quantity]);
        $saleElements = $product->getDefaultSaleElements();
        $saleElements
            ->setQuantity($quantity)
            ->setOutOfStockPolicy($mode)
            ->setPreorderLimit($limit)
            ->setRestockDate($restockDate)
            ->save($this->getPropelConnection());

        return $saleElements;
    }

    /**
     * @return array<string, mixed>
     */
    private function front(ProductSaleElements $saleElements): array
    {
        $response = $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$saleElements->getId());
        self::assertJsonResponseSuccessful($response);

        return self::decodeJson($response);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function psesOf(Product $product): array
    {
        return json_decode(self::getContainer()->get(ProductSaleElementsAccessService::class)->psesByProduct($product->getId()), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $pse
     *
     * @return array<string, mixed>
     */
    private function subset(array $pse): array
    {
        $subset = array_intersect_key($pse, array_flip(['quantity', 'availableQuantity', 'outOfStockPolicy', 'restockDate', 'isPreorder']));
        $subset['availableQuantity'] = self::asFloat($subset['availableQuantity']);

        return $subset;
    }

    private static function asFloat(int|float|null $value): ?float
    {
        return null === $value ? null : (float) $value;
    }

    private function addToCart(int $cartId, ProductSaleElements $saleElements, int $quantity, string $token): \Symfony\Component\HttpFoundation\Response
    {
        return $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cartId,
            'productSaleElements' => '/api/front/product_sale_elements/'.$saleElements->getId(),
            'quantity' => $quantity,
        ], $token);
    }
}
