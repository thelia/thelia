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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Currency;
use Thelia\Model\Map\SaleUnitProductPriceTableMap;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\SaleUnitProductPriceQuery;
use Thelia\Model\SaleUnitProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * What a sale unit owns of the catalog, its prices and its stocks, is managed through
 * the admin API, under the sale unit right: written, read back, filtered by unit and
 * by sale element, paginated, and refused to whoever holds no such right.
 */
final class SaleUnitCatalogApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->currency = $this->factory->currency();
    }

    public function testAUnitPriceIsCreatedReadChangedAndRemoved(): void
    {
        $token = $this->authenticateAsAdmin();
        $saleUnit = $this->factory->saleUnit();
        $productSaleElements = $this->productSaleElements();

        $created = $this->jsonRequest('POST', '/api/admin/sale_unit_product_prices', [
            'saleUnit' => '/api/admin/sale_units/'.$saleUnit->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$productSaleElements->getId(),
            'currency' => '/api/admin/currencies/'.$this->currency->getId(),
            'price' => 12.5,
            'promoPrice' => 10.0,
        ], $token);
        self::assertJsonResponseSuccessful($created);
        $id = $this->decode($created)['id'];

        $row = SaleUnitProductPriceQuery::create()->findPk($id, $this->getPropelConnection());
        self::assertNotNull($row);
        self::assertSame($saleUnit->getId(), $row->getSaleUnitId());
        self::assertSame($productSaleElements->getId(), $row->getProductSaleElementsId());
        self::assertEqualsWithDelta(12.5, (float) $row->getPrice(), 0.0001);

        $read = $this->jsonRequest('GET', '/api/admin/sale_unit_product_prices/'.$id, token: $token);
        self::assertJsonResponseSuccessful($read);
        self::assertEqualsWithDelta(10.0, $this->decode($read)['promoPrice'], 0.0001);

        $changed = $this->jsonRequest('PATCH', '/api/admin/sale_unit_product_prices/'.$id, ['price' => 14.0], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($changed);
        SaleUnitProductPriceTableMap::clearInstancePool();
        self::assertEqualsWithDelta(14.0, (float) SaleUnitProductPriceQuery::create()->findPk($id, $this->getPropelConnection())?->getPrice(), 0.0001);

        $removed = $this->jsonRequest('DELETE', '/api/admin/sale_unit_product_prices/'.$id, token: $token);
        self::assertSame(204, $removed->getStatusCode(), (string) $removed->getContent());
        SaleUnitProductPriceTableMap::clearInstancePool();
        self::assertNull(SaleUnitProductPriceQuery::create()->findPk($id, $this->getPropelConnection()));
    }

    public function testAUnitStockIsCreatedReadChangedAndRemoved(): void
    {
        $token = $this->authenticateAsAdmin();
        $saleUnit = $this->factory->saleUnit();
        $productSaleElements = $this->productSaleElements();

        $created = $this->jsonRequest('POST', '/api/admin/sale_unit_product_sale_elements', [
            'saleUnit' => '/api/admin/sale_units/'.$saleUnit->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$productSaleElements->getId(),
            'quantity' => 7,
        ], $token);
        self::assertJsonResponseSuccessful($created);
        $id = $this->decode($created)['id'];

        $read = $this->jsonRequest('GET', '/api/admin/sale_unit_product_sale_elements/'.$id, token: $token);
        self::assertJsonResponseSuccessful($read);
        self::assertEqualsWithDelta(7.0, $this->decode($read)['quantity'], 0.0001);

        $changed = $this->jsonRequest('PATCH', '/api/admin/sale_unit_product_sale_elements/'.$id, ['quantity' => 3], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($changed);
        SaleUnitProductSaleElementsTableMap::clearInstancePool();
        self::assertEqualsWithDelta(3.0, (float) SaleUnitProductSaleElementsQuery::create()->findPk($id, $this->getPropelConnection())?->getQuantity(), 0.0001);

        $removed = $this->jsonRequest('DELETE', '/api/admin/sale_unit_product_sale_elements/'.$id, token: $token);
        self::assertSame(204, $removed->getStatusCode(), (string) $removed->getContent());
        SaleUnitProductSaleElementsTableMap::clearInstancePool();
        self::assertNull(SaleUnitProductSaleElementsQuery::create()->findPk($id, $this->getPropelConnection()));
    }

    public function testTheStocksAreFilteredByUnitAndBySaleElementAndPaginated(): void
    {
        $token = $this->authenticateAsAdmin();
        $first = $this->factory->saleUnit();
        $second = $this->factory->saleUnit();
        $one = $this->productSaleElements();
        $two = $this->productSaleElements();

        $this->factory->saleUnitProductSaleElements($first, $one, 1.0);
        $this->factory->saleUnitProductSaleElements($first, $two, 2.0);
        $this->factory->saleUnitProductSaleElements($second, $one, 3.0);

        $ofTheFirstUnit = $this->jsonRequest('GET', '/api/admin/sale_unit_product_sale_elements?saleUnit.id='.$first->getId(), token: $token);
        self::assertJsonResponseSuccessful($ofTheFirstUnit);
        self::assertEqualsCanonicalizing([1.0, 2.0], $this->quantities($ofTheFirstUnit));

        $ofTheFirstSaleElement = $this->jsonRequest('GET', '/api/admin/sale_unit_product_sale_elements?productSaleElements.id='.$one->getId(), token: $token);
        self::assertJsonResponseSuccessful($ofTheFirstSaleElement);
        self::assertEqualsCanonicalizing([1.0, 3.0], $this->quantities($ofTheFirstSaleElement));

        $page = $this->jsonRequest('GET', '/api/admin/sale_unit_product_sale_elements?itemsPerPage=1&saleUnit.id='.$first->getId(), token: $token);
        self::assertJsonResponseSuccessful($page);
        self::assertHydraTotalItems(2, $page);
        self::assertCount(1, $this->decode($page)['hydra:member']);
    }

    public function testThePricesAreFilteredByUnitAndBySaleElement(): void
    {
        $token = $this->authenticateAsAdmin();
        $first = $this->factory->saleUnit();
        $second = $this->factory->saleUnit();
        $one = $this->productSaleElements();
        $two = $this->productSaleElements();

        $this->factory->saleUnitProductPrice($first, $one, $this->currency, 1.0);
        $this->factory->saleUnitProductPrice($first, $two, $this->currency, 2.0);
        $this->factory->saleUnitProductPrice($second, $one, $this->currency, 3.0);

        $ofTheSecondUnit = $this->jsonRequest('GET', '/api/admin/sale_unit_product_prices?saleUnit.id='.$second->getId(), token: $token);
        self::assertJsonResponseSuccessful($ofTheSecondUnit);
        self::assertSame([3.0], array_map('floatval', array_column($this->decode($ofTheSecondUnit)['hydra:member'], 'price')));

        $ofTheSecondSaleElement = $this->jsonRequest('GET', '/api/admin/sale_unit_product_prices?productSaleElements.id='.$two->getId(), token: $token);
        self::assertJsonResponseSuccessful($ofTheSecondSaleElement);
        self::assertSame([2.0], array_map('floatval', array_column($this->decode($ofTheSecondSaleElement)['hydra:member'], 'price')));
    }

    public function testASecondStockForTheSameUnitAndSaleElementIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();
        $saleUnit = $this->factory->saleUnit();
        $productSaleElements = $this->productSaleElements();
        $this->factory->saleUnitProductSaleElements($saleUnit, $productSaleElements, 1.0);

        $response = $this->jsonRequest('POST', '/api/admin/sale_unit_product_sale_elements', [
            'saleUnit' => '/api/admin/sale_units/'.$saleUnit->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$productSaleElements->getId(),
            'quantity' => 5,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAnAnonymousCallerIsRefused(): void
    {
        foreach (['/api/admin/sale_unit_product_prices', '/api/admin/sale_unit_product_sale_elements'] as $uri) {
            self::assertSame(401, $this->jsonRequest('GET', $uri)->getStatusCode(), $uri);
            self::assertSame(401, $this->jsonRequest('POST', $uri, ['quantity' => 1, 'price' => 1])->getStatusCode(), $uri);
        }
    }

    public function testAnAdministratorWithoutTheSaleUnitRightIsRefused(): void
    {
        $saleUnit = $this->factory->saleUnit();
        $productSaleElements = $this->productSaleElements();
        $admin = $this->factory->restrictedAdmin([AdminResources::PRODUCT => [
            AccessManager::VIEW,
            AccessManager::CREATE,
            AccessManager::UPDATE,
            AccessManager::DELETE,
        ]]);
        $token = $this->authenticateAsAdmin($admin);

        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/sale_unit_product_sale_elements', token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/sale_unit_product_sale_elements', [
            'saleUnit' => '/api/admin/sale_units/'.$saleUnit->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$productSaleElements->getId(),
            'quantity' => 5,
        ], $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/sale_unit_product_prices', token: $token)->getStatusCode());
        self::assertSame(0, SaleUnitProductSaleElementsQuery::create()->filterBySaleUnitId($saleUnit->getId())->count($this->getPropelConnection()));
    }

    public function testAnAdministratorGrantedTheSaleUnitRightReachesThem(): void
    {
        $saleUnit = $this->factory->saleUnit();
        $productSaleElements = $this->productSaleElements();
        $admin = $this->factory->restrictedAdmin([AdminResources::SALE_UNIT => [
            AccessManager::VIEW,
            AccessManager::CREATE,
            AccessManager::UPDATE,
            AccessManager::DELETE,
        ]]);
        $token = $this->authenticateAsAdmin($admin);

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/sale_unit_product_prices', token: $token));
        self::assertJsonResponseSuccessful($this->jsonRequest('POST', '/api/admin/sale_unit_product_sale_elements', [
            'saleUnit' => '/api/admin/sale_units/'.$saleUnit->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$productSaleElements->getId(),
            'quantity' => 5,
        ], $token));
    }

    private function productSaleElements(): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency);

        return $product->getDefaultSaleElements();
    }

    /**
     * @return list<float>
     */
    private function quantities(Response $response): array
    {
        return array_map('floatval', array_column($this->decode($response)['hydra:member'], 'quantity'));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
