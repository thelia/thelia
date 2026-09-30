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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The purchase lists of an account through the API. Every operation on one list
 * answers a list of another account exactly as it answers an id never issued.
 */
final class PurchaseListApiTest extends ApiTestCase
{
    private const string BASE = '/api/front/account/purchase-lists';

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testAListGoesThroughItsWholeLife(): void
    {
        $token = $this->authenticateAsCustomer($this->customer());
        $this->productWithSaleElement('LIFE-A');
        [, $second] = $this->productWithSaleElement('LIFE-B');

        $created = $this->jsonRequest('POST', self::BASE, ['title' => '  Monthly   restock ', 'lines' => [['reference' => 'LIFE-A', 'quantity' => 2]]], $token, 'json');
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        $list = self::decode($created);
        self::assertSame('Monthly restock', $list['title']);
        self::assertTrue($list['canWrite']);
        self::assertFalse($list['shared']);
        self::assertSame([['reference' => 'LIFE-A', 'quantity' => 2, 'productSaleElementsId' => null]], $list['items']);
        $item = self::BASE.'/'.$list['id'];

        $collection = self::decode($this->jsonRequest('GET', self::BASE, token: $token, format: 'json'));
        self::assertSame([$list['id']], array_column($collection, 'id'));
        self::assertSame(1, $collection[0]['itemCount']);
        self::assertArrayNotHasKey('items', $collection[0]);

        $renamed = $this->jsonRequest('PATCH', $item, ['title' => 'Weekly restock'], $token, 'merge-patch+json');
        self::assertSame(200, $renamed->getStatusCode(), (string) $renamed->getContent());
        self::assertSame('Weekly restock', self::decode($renamed)['title']);

        $appended = $this->jsonRequest('POST', $item.'/items', ['lines' => [['reference' => 'LIFE-A', 'quantity' => 3], ['reference' => 'LIFE-B', 'quantity' => 1, 'productSaleElementsId' => (int) $second->getId()]]], $token, 'json');
        self::assertSame(200, $appended->getStatusCode(), (string) $appended->getContent());
        self::assertSame([
            ['reference' => 'LIFE-A', 'quantity' => 5, 'productSaleElementsId' => null],
            ['reference' => 'LIFE-B', 'quantity' => 1, 'productSaleElementsId' => (int) $second->getId()],
        ], self::decode($appended)['items']);

        $replaced = $this->jsonRequest('PUT', $item.'/items', ['lines' => [['reference' => 'LIFE-B', 'quantity' => 4, 'productSaleElementsId' => (int) $second->getId()]]], $token, 'json');
        self::assertSame(200, $replaced->getStatusCode(), (string) $replaced->getContent());
        self::assertSame(1, self::decode($replaced)['itemCount']);

        $table = $this->jsonRequest('GET', $item.'/table', token: $token, format: 'json');
        self::assertSame(200, $table->getStatusCode(), (string) $table->getContent());
        self::assertSame(['resolved'], array_column(self::decode($table)['lines'], 'status'));
        self::assertSame((int) $second->getId(), self::decode($table)['lines'][0]['productSaleElementsId']);

        $copy = $this->jsonRequest('POST', $item.'/duplicate', ['title' => null], $token, 'json');
        self::assertSame(201, $copy->getStatusCode(), (string) $copy->getContent());
        self::assertNotSame($list['id'], self::decode($copy)['id']);
        self::assertSame('Weekly restock', self::decode($copy)['title']);
        self::assertSame(1, self::decode($copy)['itemCount']);

        self::assertSame(204, $this->jsonRequest('DELETE', $item, token: $token, format: 'json')->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', $item, token: $token, format: 'json')->getStatusCode());
        self::assertNull(CustomerListQuery::create()->findPk($list['id']));
        self::assertNotNull(CustomerListQuery::create()->findPk(self::decode($copy)['id']));
    }

    public function testACartOfTheAccountIsSavedAsAList(): void
    {
        $customer = $this->customer();
        [$product, $saleElements] = $this->productWithSaleElement('CART-A');
        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product, $saleElements, ['quantity' => 2.0]);

        $response = $this->jsonRequest('POST', self::BASE.'/from-cart/'.$cart->getId(), ['title' => 'From my cart'], $this->authenticateAsCustomer($customer), 'json');

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([['reference' => 'CART-A', 'quantity' => 2, 'productSaleElementsId' => (int) $saleElements->getId()]], self::decode($response)['items']);
    }

    public function testTheCartOfAnotherAccountAnswersAsACartThatDoesNotExist(): void
    {
        $customer = $this->customer();
        $token = $this->authenticateAsCustomer($customer);
        $cart = $this->factory->cart($this->customer());

        $foreign = $this->jsonRequest('POST', self::BASE.'/from-cart/'.$cart->getId(), ['title' => 'Not mine'], $token, 'json');
        $missing = $this->jsonRequest('POST', self::BASE.'/from-cart/999999999', ['title' => 'Not mine'], $token, 'json');

        self::assertSame(404, $foreign->getStatusCode(), (string) $foreign->getContent());
        self::assertSame(self::errorOf($missing), self::errorOf($foreign));
        self::assertSame(0, CustomerListQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    public function testAPastOrderOfTheAccountIsSavedAsAList(): void
    {
        $customer = $this->customer();
        [$product, $saleElements] = $this->productWithSaleElement('ORDER-A');
        $order = $this->factory->order($customer);
        $this->orderLine((int) $order->getId(), $product, $saleElements, 3.0);

        $response = $this->jsonRequest('POST', self::BASE.'/from-order/'.$order->getId(), ['title' => 'From my order'], $this->authenticateAsCustomer($customer), 'json');

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([['reference' => 'ORDER-A', 'quantity' => 3, 'productSaleElementsId' => (int) $saleElements->getId()]], self::decode($response)['items']);
    }

    public function testTheOrderOfAnotherAccountAnswersAsAnOrderThatDoesNotExist(): void
    {
        $customer = $this->customer();
        $token = $this->authenticateAsCustomer($customer);
        $order = $this->factory->order($this->customer());

        $foreign = $this->jsonRequest('POST', self::BASE.'/from-order/'.$order->getId(), ['title' => 'Not mine'], $token, 'json');
        $missing = $this->jsonRequest('POST', self::BASE.'/from-order/999999999', ['title' => 'Not mine'], $token, 'json');

        self::assertSame(404, $foreign->getStatusCode(), (string) $foreign->getContent());
        self::assertSame(self::errorOf($missing), self::errorOf($foreign));
        self::assertSame(0, CustomerListQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}>
     */
    public static function listOperations(): iterable
    {
        $lines = ['lines' => [['reference' => 'ANY', 'quantity' => 1]]];

        yield 'read' => ['GET', '', [], 'json'];
        yield 'table' => ['GET', '/table', [], 'json'];
        yield 'rename' => ['PATCH', '', ['title' => 'Taken over'], 'merge-patch+json'];
        yield 'deletion' => ['DELETE', '', [], 'json'];
        yield 'copy' => ['POST', '/duplicate', ['title' => null], 'json'];
        yield 'lines added' => ['POST', '/items', $lines, 'json'];
        yield 'lines replaced' => ['PUT', '/items', $lines, 'json'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('listOperations')]
    public function testAListOfAnotherAccountAnswersAsAListThatDoesNotExist(string $method, string $suffix, array $body, string $format): void
    {
        $owner = $this->customer();
        $list = $this->getService(PurchaseListFacade::class)->create($owner, 'Mine only');
        $token = $this->authenticateAsCustomer($this->customer());

        $foreign = $this->jsonRequest($method, self::BASE.'/'.$list->getId().$suffix, $body, $token, $format);
        $missing = $this->jsonRequest($method, self::BASE.'/999999999'.$suffix, $body, $token, $format);

        self::assertSame(404, $foreign->getStatusCode(), (string) $foreign->getContent());
        self::assertSame(self::errorOf($missing), self::errorOf($foreign));

        $kept = CustomerListQuery::create()->findPk($list->getId());
        self::assertInstanceOf(CustomerList::class, $kept);
        self::assertSame('Mine only', $kept->getTitle());
        self::assertSame(1, CustomerListQuery::create()->filterByCustomerId($owner->getId())->count());
    }

    public function testTheCollectionOnlyHoldsTheListsOfTheAccount(): void
    {
        $facade = $this->getService(PurchaseListFacade::class);
        $customer = $this->customer();
        $mine = $facade->create($customer, 'Mine');
        $facade->create($this->customer(), 'Somebody else\'s');

        $collection = self::decode($this->jsonRequest('GET', self::BASE, token: $this->authenticateAsCustomer($customer), format: 'json'));

        self::assertSame([(int) $mine->getId()], array_column($collection, 'id'));
    }

    public function testALineWhoseSaleElementWasDeletedIsShownRatherThanDropped(): void
    {
        $customer = $this->customer();
        [, $kept] = $this->productWithSaleElement('KEPT-REF');
        [, $removed] = $this->productWithSaleElement('GONE-REF');
        $token = $this->authenticateAsCustomer($customer);
        $created = self::decode($this->jsonRequest('POST', self::BASE, ['title' => 'Old list', 'lines' => [
            ['reference' => 'KEPT-REF', 'quantity' => 1, 'productSaleElementsId' => (int) $kept->getId()],
            ['reference' => 'GONE-REF', 'quantity' => 2, 'productSaleElementsId' => (int) $removed->getId()],
        ]], $token, 'json'));
        $removed->delete();

        $table = $this->jsonRequest('GET', self::BASE.'/'.$created['id'].'/table', token: $token, format: 'json');

        self::assertSame(200, $table->getStatusCode(), (string) $table->getContent());
        $lines = self::decode($table)['lines'];
        self::assertSame(['KEPT-REF', 'GONE-REF'], array_column($lines, 'reference'));
        self::assertSame(['resolved', 'unknown'], array_column($lines, 'status'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}>
     */
    public static function refusedBodies(): iterable
    {
        yield 'a creation without a title' => ['POST', '', ['title' => '   '], 'json'];
        yield 'a title of 256 characters' => ['POST', '', ['title' => str_repeat('a', 256)], 'json'];
        yield 'a line carrying a price' => ['POST', '', ['title' => 'Priced', 'lines' => [['reference' => 'ABC', 'quantity' => 1, 'price' => 0.01]]], 'json'];
        yield 'a creation of 501 lines' => ['POST', '', ['title' => 'Too long', 'lines' => self::manyLines(501)], 'json'];
        yield 'a rename without a title' => ['PATCH', '/{id}', ['title' => ''], 'merge-patch+json'];
        yield 'a rename carrying lines' => ['PATCH', '/{id}', ['title' => 'Renamed', 'lines' => [['reference' => 'ABC', 'quantity' => 1]]], 'merge-patch+json'];
        yield 'lines added with a zero quantity' => ['POST', '/{id}/items', ['lines' => [['reference' => 'ABC', 'quantity' => 0]]], 'json'];
        yield 'no line to add' => ['POST', '/{id}/items', ['lines' => []], 'json'];
        yield 'more than 500 lines once added' => ['POST', '/{id}/items', ['lines' => self::manyLines(500, 'OTHER-')], 'json'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('refusedBodies')]
    public function testABodyTheShopCannotTakeIsRefused(string $method, string $path, array $body, string $format): void
    {
        $customer = $this->customer();
        $list = $this->getService(PurchaseListFacade::class)->create($customer, 'Existing', new ReferenceQuantityLines([new ReferenceQuantity('FIRST', 1)]));

        $response = $this->jsonRequest($method, self::BASE.str_replace('{id}', (string) $list->getId(), $path), $body, $this->authenticateAsCustomer($customer), $format);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAnAccountKeepsAtMostOneHundredLists(): void
    {
        $customer = $this->customer();

        for ($n = 1; $n <= PurchaseListFacade::MAX_LISTS_PER_CUSTOMER; ++$n) {
            (new CustomerList())
                ->setCustomerId($customer->getId())
                ->setTitle('List '.$n)
                ->save($this->getPropelConnection());
        }

        $response = $this->jsonRequest('POST', self::BASE, ['title' => 'One too many'], $this->authenticateAsCustomer($customer), 'json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(PurchaseListFacade::MAX_LISTS_PER_CUSTOMER, CustomerListQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    public function testLoadingAListCountsAgainstTheQuickOrderRateLimit(): void
    {
        $customer = $this->customer();
        $list = $this->getService(PurchaseListFacade::class)->create($customer, 'Often loaded');
        $token = $this->authenticateAsCustomer($customer);

        for ($n = 1; $n <= 29; ++$n) {
            self::assertSame(200, $this->jsonRequest('GET', self::BASE.'/'.$list->getId().'/table', token: $token, format: 'json')->getStatusCode(), 'Request '.$n.' was refused.');
        }

        self::assertSame(200, $this->jsonRequest('POST', '/api/front/account/quick-order/resolve', ['lines' => [['reference' => 'ANY', 'quantity' => 1]]], $token, 'json')->getStatusCode());
        self::assertSame(429, $this->jsonRequest('GET', self::BASE.'/'.$list->getId().'/table', token: $token, format: 'json')->getStatusCode());
    }

    /**
     * @return list<array{reference: string, quantity: int}>
     */
    private static function manyLines(int $count, string $prefix = 'REF-'): array
    {
        return array_map(static fn (int $n): array => ['reference' => $prefix.$n, 'quantity' => 1], range(1, $count));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * The answer without what legitimately differs between two requests.
     *
     * @return array<string, mixed>
     */
    private static function errorOf(Response $response): array
    {
        $error = self::decode($response);
        unset($error['trace'], $error['@id'], $error['id'], $error['instance']);

        return ['status' => $response->getStatusCode(), 'error' => $error];
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * @return array{Product, ProductSaleElements}
     */
    private function productWithSaleElement(string $reference): array
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['baseQuantity' => 50]);
        $saleElements = $this->factory->productSaleElement($product, ['ref' => $reference, 'quantity' => 50]);
        $this->factory->productPrice($saleElements, $currency);

        return [$product, $saleElements];
    }

    private function orderLine(int $orderId, Product $product, ProductSaleElements $saleElements, float $quantity): void
    {
        (new OrderProduct())
            ->setOrderId($orderId)
            ->setProductRef($product->getRef())
            ->setProductSaleElementsRef($saleElements->getRef())
            ->setProductSaleElementsId($saleElements->getId())
            ->setTitle('Ordered line')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());
    }
}
