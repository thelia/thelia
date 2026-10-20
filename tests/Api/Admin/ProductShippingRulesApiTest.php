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
use Thelia\Model\Admin;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductExcludedDeliveryModule;
use Thelia\Model\ProductExcludedDeliveryModuleQuery;
use Thelia\Model\ProductQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;

/**
 * The shipping rules of a product through the admin API, the way a catalogue import
 * writes them.
 */
final class ProductShippingRulesApiTest extends ApiTestCase
{
    public function testAPatchWritesTheShippingRules(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'shippingSurcharge' => 7.5,
            'shippingSurchargeMode' => 1,
            'freeShipping' => true,
            'i18ns' => ['en_US' => ['title' => 'Bulky parcel', 'locale' => 'en_US']],
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertEqualsWithDelta(7.5, (float) $reloaded->getShippingSurcharge(), 0.000001);
        self::assertSame(1, $reloaded->getShippingSurchargeMode());
        self::assertSame(1, $reloaded->getFreeShipping());
    }

    public function testAPutThatDoesNotCarryTheRulesKeepsThem(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $product = $factory->product($category, $taxRule, $factory->currency(), ['title' => 'Bulky parcel']);
        $product->setShippingSurcharge('3')->setShippingSurchargeMode(1)->setFreeShipping(1)->save();

        // A PUT clears the writable fields it omits: an import written before the
        // rules existed must not wipe them.
        $response = $this->jsonRequest('PUT', '/api/admin/products/'.$product->getId(), [
            'ref' => $product->getRef(),
            'visible' => true,
            'virtual' => false,
            'taxRule' => '/api/admin/tax_rules/'.$taxRule->getId(),
            'i18ns' => ['en_US' => ['title' => 'Bulky parcel', 'locale' => 'en_US']],
            'productCategories' => [['category' => '/api/admin/categories/'.$category->getId(), 'defaultCategory' => true]],
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertEqualsWithDelta(3.0, (float) $reloaded->getShippingSurcharge(), 0.000001);
        self::assertSame(1, $reloaded->getShippingSurchargeMode());
        self::assertSame(1, $reloaded->getFreeShipping());
    }

    public function testACarrierIsExcludedThenAllowedAgain(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();
        $carrier = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne();
        self::assertNotNull($carrier);

        $response = $this->jsonRequest('POST', '/api/admin/product_excluded_delivery_modules', [
            'product' => '/api/admin/products/'.$product->getId(),
            'module' => '/api/admin/modules/'.$carrier->getId(),
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $exclusion = ProductExcludedDeliveryModuleQuery::create()->filterByProductId($product->getId())->filterByModuleId($carrier->getId())->findOne();
        self::assertNotNull($exclusion);

        $response = $this->jsonRequest('DELETE', '/api/admin/product_excluded_delivery_modules/'.$exclusion->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode());
        self::assertFalse(ProductExcludedDeliveryModuleQuery::create()->filterByProductId($product->getId())->exists());
    }

    public function testAModuleThatIsNotACarrierCannotBeExcluded(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();
        $paymentModule = ModuleQuery::create()->filterByType(BaseModule::PAYMENT_MODULE_TYPE)->findOne();
        self::assertNotNull($paymentModule);

        $response = $this->jsonRequest('POST', '/api/admin/product_excluded_delivery_modules', [
            'product' => '/api/admin/products/'.$product->getId(),
            'module' => '/api/admin/modules/'.$paymentModule->getId(),
        ], $token);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse(ProductExcludedDeliveryModuleQuery::create()->filterByProductId($product->getId())->exists());
    }

    public function testEveryWriteOfTheShippingRulesWantsAToken(): void
    {
        [$product, $carrier] = [$this->product(), $this->carrier()];
        $exclusionId = $this->exclusionOf($product, $carrier);

        foreach ($this->shippingRuleWrites($product, $carrier, $exclusionId) as $what => [$method, $uri, $payload, $format]) {
            self::assertSame(401, $this->jsonRequest($method, $uri, $payload, null, $format)->getStatusCode(), $what);
        }
    }

    public function testAnAdministratorWithoutTheCatalogueCannotWriteTheShippingRules(): void
    {
        $this->assertTheShippingRulesAreRefusedTo($this->createFixtureFactory()->restrictedAdmin([
            AdminResources::CATEGORY => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE],
        ]));
    }

    public function testAnAdministratorWhoMayOnlyLookAtTheCatalogueCannotWriteTheShippingRules(): void
    {
        $this->assertTheShippingRulesAreRefusedTo($this->createFixtureFactory()->restrictedAdmin([
            AdminResources::PRODUCT => [AccessManager::VIEW],
        ]));
    }

    public function testAnAdministratorWhoMayEditTheCatalogueWritesTheShippingRules(): void
    {
        $token = $this->authenticateAsAdmin($this->createFixtureFactory()->restrictedAdmin([
            AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE],
        ]));
        [$product, $carrier] = [$this->product(), $this->carrier()];

        self::assertJsonResponseSuccessful($this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'shippingSurcharge' => 4.0,
            'shippingSurchargeMode' => 1,
            'freeShipping' => true,
            'i18ns' => ['en_US' => ['title' => 'Bulky parcel', 'locale' => 'en_US']],
        ], $token, 'merge-patch+json'));
        $response = $this->jsonRequest('POST', '/api/admin/product_excluded_delivery_modules', [
            'product' => '/api/admin/products/'.$product->getId(),
            'module' => '/api/admin/modules/'.$carrier->getId(),
        ], $token);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $exclusion = ProductExcludedDeliveryModuleQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($exclusion);
        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/product_excluded_delivery_modules/'.$exclusion->getId(), token: $token)->getStatusCode());

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertEqualsWithDelta(4.0, (float) $reloaded->getShippingSurcharge(), 0.000001);
    }

    private function assertTheShippingRulesAreRefusedTo(Admin $admin): void
    {
        $token = $this->authenticateAsAdmin($admin);
        [$product, $carrier] = [$this->product(), $this->carrier()];
        $exclusionId = $this->exclusionOf($product, $carrier);

        foreach ($this->shippingRuleWrites($product, $carrier, $exclusionId) as $what => [$method, $uri, $payload, $format]) {
            self::assertSame(403, $this->jsonRequest($method, $uri, $payload, $token, $format)->getStatusCode(), $what);
        }

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertEqualsWithDelta(0.0, (float) $reloaded->getShippingSurcharge(), 0.000001);
        self::assertSame(1, ProductExcludedDeliveryModuleQuery::create()->filterByProductId($product->getId())->count(), 'The exclusion is neither removed nor doubled.');
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}>
     */
    private function shippingRuleWrites(Product $product, Module $carrier, int $exclusionId): iterable
    {
        yield 'rules of the product' => ['PATCH', '/api/admin/products/'.$product->getId(), ['shippingSurcharge' => 9.0, 'shippingSurchargeMode' => 1, 'freeShipping' => true, 'i18ns' => ['en_US' => ['title' => 'Bulky parcel', 'locale' => 'en_US']]], 'merge-patch+json'];
        yield 'exclusion added' => ['POST', '/api/admin/product_excluded_delivery_modules', ['product' => '/api/admin/products/'.$product->getId(), 'module' => '/api/admin/modules/'.$carrier->getId()], 'jsonld'];
        yield 'exclusion removed' => ['DELETE', '/api/admin/product_excluded_delivery_modules/'.$exclusionId, [], 'jsonld'];
    }

    private function exclusionOf(Product $product, Module $carrier): int
    {
        $exclusion = (new ProductExcludedDeliveryModule())->setProductId($product->getId())->setModuleId($carrier->getId());
        $exclusion->save();

        return $exclusion->getId();
    }

    private function carrier(): Module
    {
        return ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne()
            ?? throw new \RuntimeException('No delivery module installed - run bin/test-prepare.');
    }

    private function product(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['title' => 'Bulky parcel']);
    }
}
