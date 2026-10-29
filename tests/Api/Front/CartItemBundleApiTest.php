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

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The bundle line through the front API: the composition is proposed by the caller and revalidated
 * against the bundle, and nothing about money is taken from the body. 10 % off a bike, two helmets
 * and two locks: 600 struck, 540 charged.
 */
final class CartItemBundleApiTest extends ApiTestCase
{
    use BuildsBundles;

    private ?string $stockCheckBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getService(PricingActivityChecker::class)->reset();
        $this->stockCheckBefore = (string) ConfigQuery::read('check-available-stock', '1');
        ConfigQuery::write('check-available-stock', '1');
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('check-available-stock', $this->stockCheckBefore ?? '1');
        ConfigQuery::resetCache();
        $this->getService(PricingActivityChecker::class)->reset();

        parent::tearDown();
    }

    public function testTheCustomerAddsABundleWithItsComposition(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();

        $response = $this->post($customer, $cart, $example['bundle'], 1, $this->lines($example, 2));

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();
        self::assertSame(600.0, (float) $line->getPrice());
        self::assertSame(540.0, (float) $line->getPromoPrice());
        self::assertSame(1, (int) $line->getPromo());
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(
            [
                ['bundleItemId' => (int) $example['bike']->getId(), 'quantity' => '1'],
                ['bundleItemId' => (int) $example['helmet']->getId(), 'quantity' => '2'],
                ['bundleItemId' => (int) $example['lock']->getId(), 'quantity' => '2'],
            ],
            $payload['bundleComposition'],
        );
    }

    public function testTheLineExposesItsCompositionOnRead(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();
        $this->post($customer, $cart, $example['bundle'], 1, $this->lines($example, 2));
        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();

        $response = $this->jsonRequest('GET', '/api/front/cart_items/'.$line->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(3, $payload['bundleComposition']);
        self::assertSame((int) $example['lock']->getId(), $payload['bundleComposition'][2]['bundleItemId']);
        self::assertSame('2', $payload['bundleComposition'][2]['quantity']);
    }

    public function testWithoutCompositionTheDefaultOneIsTaken(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();

        $response = $this->post($customer, $cart, $example['bundle'], 1);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();
        self::assertSame(560.0, (float) $line->getPrice());
        self::assertSame(504.0, (float) $line->getPromoPrice());
    }

    public function testAnItemOfAnotherBundleIsRefused(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();
        $other = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        $lines = $this->lines($example, 1);
        $lines[2]['bundleItemId'] = (int) $other['lock']->getId();

        $response = $this->post($customer, $cart, $example['bundle'], 1, $lines);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->lineCount($cart));
    }

    public function testAQuantityOutOfBoundsIsRefused(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();

        $response = $this->post($customer, $cart, $example['bundle'], 1, $this->lines($example, 4));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->lineCount($cart));
    }

    public function testARequiredComponentLeftOutIsRefused(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();

        $response = $this->post($customer, $cart, $example['bundle'], 1, [['bundleItemId' => (int) $example['helmet']->getId(), 'quantity' => 2]]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->lineCount($cart));
    }

    public function testAMalformedCompositionIsRefusedAndNotAnError(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();

        $response = $this->post($customer, $cart, $example['bundle'], 1, [['bundleItemId' => 'abc', 'quantity' => 'many']]);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, $this->lineCount($cart));
    }

    public function testACompositionForAProductThatIsNotABundleIsRefused(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();

        $response = $this->post($customer, $cart, $example['bikeProduct'], 1, $this->lines($example, 2));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->lineCount($cart));
    }

    public function testAQuantityBeyondWhatTheComponentsSupplyIsRefusedOutLoud(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();
        $this->post($customer, $cart, $example['bundle'], 2, $this->lines($example, 2));

        $response = $this->post($customer, $cart, $example['bundle'], 1, $this->lines($example, 2));

        self::assertSame(422, $response->getStatusCode());
        CartItemTableMap::clearInstancePool();
        self::assertSame(2.0, (float) CartItemQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection())->getQuantity());
    }

    public function testARequiredComponentOutOfStockRefusesTheAddition(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();
        $this->saleElementsOf($example['helmetProduct'])->setQuantity(1)->save();

        $response = $this->post($customer, $cart, $example['bundle'], 1, $this->lines($example, 2));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->lineCount($cart));
    }

    public function testTheCompositionOfALineIsNotChangedByAPut(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();
        $this->post($customer, $cart, $example['bundle'], 1, $this->lines($example, 2));
        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), [
            'quantity' => 1,
            'bundleComposition' => $this->lines($example, 1),
        ], $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        CartItemTableMap::clearInstancePool();
        self::assertSame($this->compositionJson($example, 2), CartItemQuery::create()->findPk($line->getId(), $this->getPropelConnection())->getBundleComposition());
    }

    public function testAPutChangesTheQuantityWithinWhatTheComponentsSupply(): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();
        $this->post($customer, $cart, $example['bundle'], 1, $this->lines($example, 2));
        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();
        $token = $this->authenticateAsCustomer($customer);

        $within = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => 2], $token);
        $beyond = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => 3], $token);

        self::assertJsonResponseSuccessful($within);
        self::assertSame(422, $beyond->getStatusCode());
        CartItemTableMap::clearInstancePool();
        $reloaded = CartItemQuery::create()->findPk($line->getId(), $this->getPropelConnection());
        self::assertSame(2.0, (float) $reloaded->getQuantity());
        self::assertSame($this->compositionJson($example, 2), $reloaded->getBundleComposition());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function quantitiesThatAreTooLargeOrMalformed(): iterable
    {
        yield 'ten to the thirteen as a string' => ['10000000000000'];
        yield 'ten to the thirteen as a JSON integer' => [10_000_000_000_000];
        yield 'the largest integer' => [\PHP_INT_MAX];
        yield 'a float beyond the integers' => [1.0e30];
        yield 'above the bound of an item' => ['1000000.5'];
        yield 'a number of two hundred digits' => [str_repeat('9', 200)];
        yield 'a text' => ['many'];
    }

    /**
     * The composition is the input of an anonymous visitor: a quantity that no arithmetic can hold is a
     * refusal of the caller (422), never an error of the server (500), and nothing is written.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('quantitiesThatAreTooLargeOrMalformed')]
    public function testAQuantityBeyondWhatTheShopCanHoldIsRefusedWith422(mixed $quantity): void
    {
        [$customer, $cart, $example] = $this->setUpABundle();

        $response = $this->post($customer, $cart, $example['bundle'], 1, [
            ['bundleItemId' => (int) $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => (int) $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => (int) $example['lock']->getId(), 'quantity' => $quantity],
        ]);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, $this->lineCount($cart));
    }

    /**
     * @return array{0: Customer, 1: Cart, 2: array<string, mixed>}
     */
    private function setUpABundle(): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer, ['currency' => Currency::getDefaultCurrency()]);
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));

        return [$customer, $cart, $example];
    }

    /**
     * @param array<string, mixed> $example
     *
     * @return list<array{bundleItemId: int, quantity: int}>
     */
    private function lines(array $example, int $locks): array
    {
        return [
            ['bundleItemId' => (int) $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => (int) $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => (int) $example['lock']->getId(), 'quantity' => $locks],
        ];
    }

    /**
     * @param array<string, mixed> $example
     */
    private function compositionJson(array $example, int $locks): string
    {
        return \Thelia\Domain\Catalog\Bundle\BundleComposition::fromArray($this->lines($example, $locks))->toCanonicalJson();
    }

    /**
     * @param list<array<string, mixed>>|null $composition
     */
    private function post(Customer $customer, Cart $cart, Product $product, int $quantity, ?array $composition = null): \Symfony\Component\HttpFoundation\Response
    {
        $body = [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$this->saleElementsOf($product)->getId(),
            'quantity' => $quantity,
        ];

        if (null !== $composition) {
            $body['bundleComposition'] = $composition;
        }

        return $this->jsonRequest('POST', '/api/front/cart_items', $body, $this->authenticateAsCustomer($customer));
    }

    private function lineCount(Cart $cart): int
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()->filterByCartId($cart->getId())->count();
    }
}
