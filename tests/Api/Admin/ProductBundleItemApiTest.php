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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\SharedCache\SharedCacheInvalidationListener;
use Thelia\Core\SharedCache\SharedCacheKey;
use Thelia\Core\SharedCache\SharedCachePurgerInterface;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Model\Map\ProductBundleItemTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductBundleItemQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Unit\Core\SharedCache\RecordingSharedCachePurger;

/**
 * The components of a bundle and the bundle settings of a product, through the admin API.
 */
final class ProductBundleItemApiTest extends ApiTestCase
{
    use BuildsBundles;

    public function testAComponentIsAddedReadUpdatedAndRemoved(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $bundle = $this->bundleProduct($factory, $this->service());
        $component = $this->component($factory, '20', 10);
        $saleElementsIri = '/api/admin/product_sale_elements/'.$this->saleElementsOf($component)->getId();

        $response = $this->jsonRequest('POST', '/api/admin/product_bundle_items', [
            'bundleProduct' => '/api/admin/products/'.$bundle->getId(),
            'productSaleElements' => $saleElementsIri,
            'quantity' => 1,
            'minQuantity' => 0,
            'maxQuantity' => 3,
            'required' => false,
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $itemId = (int) self::decodeJson($response)['id'];
        $stored = ProductBundleItemQuery::create()->findPk($itemId);
        self::assertNotNull($stored);
        self::assertEqualsWithDelta(3.0, (float) $stored->getMaxQuantity(), 0.000001);
        self::assertSame(0, (int) $stored->getRequired());

        $response = $this->jsonRequest('GET', '/api/admin/product_bundle_items?bundleProduct.id='.$bundle->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);
        self::assertJsonCollectionHasCount(1, $response);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_bundle_items/'.$itemId, ['maxQuantity' => 5], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($response);
        self::assertEqualsWithDelta(5.0, (float) ProductBundleItemQuery::create()->findPk($itemId)?->getMaxQuantity(), 0.000001);

        $response = $this->jsonRequest('DELETE', '/api/admin/product_bundle_items/'.$itemId, token: $token);
        self::assertSame(204, $response->getStatusCode());
        self::assertNull(ProductBundleItemQuery::create()->findPk($itemId));
    }

    public function testABundleInsideABundleAnswers422AndWritesNothing(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $inner = $this->bundleProduct($factory, $this->service());
        $outer = $this->bundleProduct($factory, $this->service());

        $response = $this->jsonRequest('POST', '/api/admin/product_bundle_items', [
            'bundleProduct' => '/api/admin/products/'.$outer->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$this->saleElementsOf($inner)->getId(),
            'quantity' => 1,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, ProductBundleItemQuery::create()->filterByBundleProductId($outer->getId())->count());
    }

    public function testAQuantityOutsideItsBoundsAnswers422(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $bundle = $this->bundleProduct($factory, $this->service());
        $component = $this->component($factory, '20', 10);

        $response = $this->jsonRequest('POST', '/api/admin/product_bundle_items', [
            'bundleProduct' => '/api/admin/products/'.$bundle->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$this->saleElementsOf($component)->getId(),
            'quantity' => 5,
            'minQuantity' => 1,
            'maxQuantity' => 3,
            'required' => false,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * A line stays in its bundle: a body that names another bundle is refused, not ignored with a 200.
     */
    public function testAnItemCannotBeMovedToAnotherBundleByAWrite(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service());
        $other = $this->bundleProduct($factory, $this->service());
        $uri = '/api/admin/product_bundle_items/'.$example['lock']->getId();

        $put = $this->jsonRequest('PUT', $uri, [
            'bundleProduct' => '/api/admin/products/'.$other->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$example['lock']->getProductSaleElementsId(),
            'quantity' => 1,
            'required' => false,
        ], $token);
        $patch = $this->jsonRequest('PATCH', $uri, ['bundleProduct' => '/api/admin/products/'.$other->getId(), 'maxQuantity' => 2], $token, 'merge-patch+json');

        self::assertSame(422, $put->getStatusCode(), (string) $put->getContent());
        self::assertSame(422, $patch->getStatusCode(), (string) $patch->getContent());
        self::assertStringContainsString('cannot be moved to another', (string) $put->getContent());
        self::assertStringContainsString('cannot be moved to another', (string) $patch->getContent());
        $stored = ProductBundleItemQuery::create()->findPk($example['lock']->getId());
        self::assertSame((int) $example['bundle']->getId(), (int) $stored?->getBundleProductId());
        self::assertEqualsWithDelta(3.0, (float) $stored?->getMaxQuantity(), 0.000001, 'Nothing else of the body was applied.');
    }

    /**
     * The carts name an item by its id: another component is another item.
     */
    public function testAWriteCannotGiveAnItemAnotherSaleElement(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service());
        $other = $this->component($factory, '15', 5);
        $uri = '/api/admin/product_bundle_items/'.$example['lock']->getId();
        $otherIri = '/api/admin/product_sale_elements/'.$this->saleElementsOf($other)->getId();

        $put = $this->jsonRequest('PUT', $uri, [
            'bundleProduct' => '/api/admin/products/'.$example['bundle']->getId(),
            'productSaleElements' => $otherIri,
            'quantity' => 1,
            'required' => false,
        ], $token);
        $patch = $this->jsonRequest('PATCH', $uri, ['productSaleElements' => $otherIri], $token, 'merge-patch+json');

        self::assertSame(422, $put->getStatusCode(), (string) $put->getContent());
        self::assertSame(422, $patch->getStatusCode(), (string) $patch->getContent());
        self::assertStringContainsString('delete it and add a new one', (string) $patch->getContent());
        ProductBundleItemTableMap::clearInstancePool();
        self::assertSame((int) $example['lock']->getProductSaleElementsId(), (int) ProductBundleItemQuery::create()->findPk($example['lock']->getId())?->getProductSaleElementsId());
    }

    public function testAWriteThatNamesTheBundleTheItemIsInStillWorks(): void
    {
        $token = $this->authenticateAsAdmin();
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->service());

        $response = $this->jsonRequest('PUT', '/api/admin/product_bundle_items/'.$example['lock']->getId(), [
            'bundleProduct' => '/api/admin/products/'.$example['bundle']->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$example['lock']->getProductSaleElementsId(),
            'quantity' => 1,
            'minQuantity' => 0,
            'maxQuantity' => 4,
            'required' => false,
        ], $token);

        self::assertJsonResponseSuccessful($response);
        ProductBundleItemTableMap::clearInstancePool();
        self::assertEqualsWithDelta(4.0, (float) ProductBundleItemQuery::create()->findPk($example['lock']->getId())?->getMaxQuantity(), 0.000001);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function quantitiesThatAreTooLarge(): iterable
    {
        yield 'ten to the thirteen' => [10_000_000_000_000];
        yield 'a float beyond the integers' => [1.0e30];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('quantitiesThatAreTooLarge')]
    public function testAQuantityBeyondWhatTheShopCanHoldAnswers422(mixed $quantity): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $bundle = $this->bundleProduct($factory, $this->service());
        $component = $this->component($factory, '20', 10);

        $response = $this->jsonRequest('POST', '/api/admin/product_bundle_items', [
            'bundleProduct' => '/api/admin/products/'.$bundle->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$this->saleElementsOf($component)->getId(),
            'quantity' => $quantity,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, ProductBundleItemQuery::create()->filterByBundleProductId($bundle->getId())->count());
    }

    public function testASaleElementAlreadyInTheBundleAnswers422AndIsNotAddedTwice(): void
    {
        $token = $this->authenticateAsAdmin();
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->service());

        $response = $this->jsonRequest('POST', '/api/admin/product_bundle_items', [
            'bundleProduct' => '/api/admin/products/'.$example['bundle']->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$example['bike']->getProductSaleElementsId(),
            'quantity' => 1,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(3, ProductBundleItemQuery::create()->filterByBundleProductId($example['bundle']->getId())->count());
    }

    public function testTheSettingsOfABundleAreWrittenByAPatchOfTheProduct(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $product = $this->component($factory, '10', 1, ['title' => 'Future bundle']);

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'isBundle' => true,
            'bundlePriceMode' => 'computed',
            'bundleDiscount' => 12.5,
            'bundleDiscountType' => 'percent',
            'i18ns' => ['en_US' => ['title' => 'Future bundle', 'locale' => 'en_US']],
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertSame(1, $reloaded?->getIsBundle());
        self::assertSame('computed', $reloaded?->getBundlePriceMode());
        self::assertEqualsWithDelta(12.5, (float) $reloaded?->getBundleDiscount(), 0.000001);
        self::assertSame('percent', $reloaded?->getBundleDiscountType());

        $data = self::decodeJson($this->jsonRequest('GET', '/api/admin/products/'.$product->getId(), token: $token));
        self::assertTrue($data['isBundle']);
        self::assertSame('computed', $data['bundlePriceMode']);
    }

    public function testAPercentDiscountAbove100Answers422(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->component($this->createFixtureFactory(), '10', 1, ['title' => 'Future bundle']);

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'isBundle' => true,
            'bundleDiscount' => 120,
            'bundleDiscountType' => 'percent',
            'i18ns' => ['en_US' => ['title' => 'Future bundle', 'locale' => 'en_US']],
        ], $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, ProductQuery::create()->findPk($product->getId())?->getIsBundle());
    }

    public function testAComponentOfABundleCannotBecomeABundleThroughTheProduct(): void
    {
        $token = $this->authenticateAsAdmin();
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->service());

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$example['bikeProduct']->getId(), [
            'isBundle' => true,
            'i18ns' => ['en_US' => ['title' => 'Bike', 'locale' => 'en_US']],
        ], $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, ProductQuery::create()->findPk($example['bikeProduct']->getId())?->getIsBundle());
    }

    public function testEveryWriteWantsAToken(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->service());

        foreach ($this->writes($example['bundle'], (int) $example['bike']->getId()) as $what => [$method, $uri, $payload, $format]) {
            self::assertSame(401, $this->jsonRequest($method, $uri, $payload, null, $format)->getStatusCode(), $what);
        }
    }

    public function testAnAdministratorWithoutTheCatalogueIsRefused(): void
    {
        $this->assertRefusedTo([AdminResources::CATEGORY => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE]]);
    }

    public function testAnAdministratorWhoMayOnlyLookAtTheCatalogueIsRefusedTheWrites(): void
    {
        $this->assertRefusedTo([AdminResources::PRODUCT => [AccessManager::VIEW]]);
    }

    public function testAPatchOfAComponentPurgesTheSharedCacheOfItsBundle(): void
    {
        $purger = new RecordingSharedCachePurger();
        static::getContainer()->set(SharedCachePurgerInterface::class, $purger);
        $token = $this->authenticateAsAdmin();
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->service());
        $this->sendWhatTheFixturesPurged($purger);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_bundle_items/'.$example['lock']->getId(), ['maxQuantity' => 5], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertContains(SharedCacheKey::forChange('product', (int) $example['bundle']->getId()), $purger->calls);
    }

    public function testADeleteOfAComponentPurgesTheSharedCacheOfItsBundle(): void
    {
        $purger = new RecordingSharedCachePurger();
        static::getContainer()->set(SharedCachePurgerInterface::class, $purger);
        $token = $this->authenticateAsAdmin();
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->service());
        $this->sendWhatTheFixturesPurged($purger);

        $response = $this->jsonRequest('DELETE', '/api/admin/product_bundle_items/'.$example['lock']->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode());
        self::assertContains(SharedCacheKey::forChange('product', (int) $example['bundle']->getId()), $purger->calls);
    }

    public function testAnAdministratorWhoMayEditTheCatalogueWritesComponents(): void
    {
        $factory = $this->createFixtureFactory();
        $token = $this->authenticateAsAdmin($factory->restrictedAdmin([
            AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE],
        ]));
        $bundle = $this->bundleProduct($factory, $this->service());
        $component = $this->component($factory, '20', 10);

        $response = $this->jsonRequest('POST', '/api/admin/product_bundle_items', [
            'bundleProduct' => '/api/admin/products/'.$bundle->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$this->saleElementsOf($component)->getId(),
            'quantity' => 1,
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @param array<string, list<string>> $grants
     */
    private function assertRefusedTo(array $grants): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service());
        $token = $this->authenticateAsAdmin($factory->restrictedAdmin($grants));

        foreach ($this->writes($example['bundle'], (int) $example['bike']->getId()) as $what => [$method, $uri, $payload, $format]) {
            self::assertSame(403, $this->jsonRequest($method, $uri, $payload, $token, $format)->getStatusCode(), $what);
        }

        self::assertSame(3, ProductBundleItemQuery::create()->filterByBundleProductId($example['bundle']->getId())->count(), 'Nothing is added or removed.');
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}>
     */
    private function writes(Product $bundle, int $itemId): iterable
    {
        yield 'item added' => ['POST', '/api/admin/product_bundle_items', ['bundleProduct' => '/api/admin/products/'.$bundle->getId(), 'quantity' => 1], 'jsonld'];
        yield 'item changed' => ['PATCH', '/api/admin/product_bundle_items/'.$itemId, ['maxQuantity' => 5], 'merge-patch+json'];
        yield 'item removed' => ['DELETE', '/api/admin/product_bundle_items/'.$itemId, [], 'jsonld'];
    }

    private function sendWhatTheFixturesPurged(RecordingSharedCachePurger $purger): void
    {
        static::getContainer()->get(SharedCacheInvalidationListener::class)->flush();
        $purger->calls = [];
    }

    private function service(): BundleCompositionService
    {
        return $this->getService(BundleCompositionService::class);
    }
}
