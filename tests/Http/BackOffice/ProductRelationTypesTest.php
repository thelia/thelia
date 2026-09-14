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

namespace Thelia\Tests\Http\BackOffice;

use BackOfficeDefaultTwigBundle\Controller\Configuration\ProductAssociationTypeController;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociationType;
use Thelia\Model\ProductAssociationTypeQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the typed product relations: one block per type on the
 * product sheet's relations tab, and the screen the types themselves are managed
 * from.
 */
final class ProductRelationTypesTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;
    private FixtureFactory $factory;
    private Category $category;
    private Currency $currency;
    private TaxRule $taxRule;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given, and one that predates the typed relations has none of
        // the screens this asserts on.
        if (!class_exists(ProductAssociationTypeController::class)) {
            self::markTestSkipped('The installed back-office theme predates the typed product relations.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);

        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->category = $this->factory->category();
        $this->currency = $this->factory->currency();
        $this->taxRule = $this->factory->taxRule();
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        parent::tearDown();
    }

    public function testTheRelatedTabShowsOneBlockPerVisibleType(): void
    {
        $this->loginAdmin();
        $product = $this->product();

        $crawler = $this->client->request('GET', '/admin/products/related/tab?product_id='.$product->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        foreach ([ProductAssociationType::CODE_ACCESSORY, ProductAssociationType::CODE_CROSS_SELLING, ProductAssociationType::CODE_UP_SELLING] as $code) {
            self::assertGreaterThan(
                0,
                $crawler->filter($this->blockSelector($code))->count(),
                \sprintf('The relations tab must offer a block for the "%s" type.', $code),
            );
        }
    }

    public function testHidingATypeTakesItsBlockOffTheTab(): void
    {
        $this->loginAdmin();
        $product = $this->product();

        $upSelling = $this->type(ProductAssociationType::CODE_UP_SELLING);
        $upSelling->setVisible(0)->save($this->getPropelConnection());

        $crawler = $this->client->request('GET', '/admin/products/related/tab?product_id='.$product->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertCount(0, $crawler->filter($this->blockSelector(ProductAssociationType::CODE_UP_SELLING)));
        self::assertGreaterThan(0, $crawler->filter($this->blockSelector(ProductAssociationType::CODE_ACCESSORY))->count());
    }

    public function testAddingAndRemovingARelationInABlock(): void
    {
        $this->loginAdmin();
        $product = $this->product();
        $related = $this->product();

        $this->addRelation($product, $related, ProductAssociationType::CODE_CROSS_SELLING);

        $relation = AccessoryQuery::create()
            ->filterByProductId((int) $product->getId())
            ->filterByAccessory((int) $related->getId())
            ->filterByTypeId($this->type(ProductAssociationType::CODE_CROSS_SELLING)->getId())
            ->findOne();

        self::assertNotNull($relation, 'The block must write the relation under its own type.');

        $this->removeRelation($product, $related, ProductAssociationType::CODE_CROSS_SELLING);

        self::assertSame(
            0,
            AccessoryQuery::create()->filterByProductId((int) $product->getId())->count(),
            'Removing from a reciprocal block takes both directions.',
        );
    }

    public function testPositionsAreCountedWithinEachBlock(): void
    {
        $this->loginAdmin();
        $product = $this->product();

        $this->addRelation($product, $this->product(), ProductAssociationType::CODE_ACCESSORY);
        $this->addRelation($product, $this->product(), ProductAssociationType::CODE_ACCESSORY);
        $firstUpSell = $this->product();
        $this->addRelation($product, $firstUpSell, ProductAssociationType::CODE_UP_SELLING);

        $upSell = AccessoryQuery::create()
            ->filterByProductId((int) $product->getId())
            ->filterByAccessory((int) $firstUpSell->getId())
            ->findOne();

        self::assertNotNull($upSell);
        self::assertSame(1, $upSell->getPosition(), 'Each block numbers its own relations from one.');
    }

    public function testTheConfigurationScreenListsTheSeededTypes(): void
    {
        $this->loginAdmin();

        $this->client->request('GET', '/admin/configuration/product-association-type');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = (string) $this->client->getResponse()->getContent();

        foreach ([ProductAssociationType::CODE_ACCESSORY, ProductAssociationType::CODE_CROSS_SELLING, ProductAssociationType::CODE_UP_SELLING] as $code) {
            self::assertStringContainsString($code, $content);
        }
    }

    public function testTheAccessoryTypeHasNoDeleteButton(): void
    {
        $this->loginAdmin();

        $accessory = $this->type(ProductAssociationType::CODE_ACCESSORY);

        $crawler = $this->client->request('GET', '/admin/configuration/product-association-type');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertCount(
            0,
            $crawler->filter(\sprintf('[data-testid="datatable-action-delete"][data-type-id="%d"]', $accessory->getId())),
            'The type the core names by code must not expose a delete button.',
        );
    }

    public function testTheConfigurationScreenIsRefusedWithoutTheRight(): void
    {
        // A profile granting the product resource and nothing else: the screen lives
        // behind the configuration resource, which this administrator lacks.
        $admin = $this->factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);

        $this->client->request('GET', '/admin/configuration/product-association-type');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    private function loginAdmin(): void
    {
        $admin = $this->factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    private function product(): Product
    {
        return $this->factory->product($this->category, $this->taxRule, $this->currency);
    }

    private function type(string $code): ProductAssociationType
    {
        $type = ProductAssociationTypeQuery::create()->filterByCode($code)->findOne();
        self::assertNotNull($type, \sprintf('The installer must have seeded the "%s" type.', $code));

        return $type;
    }

    private function blockSelector(string $code): string
    {
        return \sprintf('[data-testid="product-relation-%s-form"]', str_replace('_', '-', $code));
    }

    /**
     * Posts the block's own form. The product select is filled in by the picker at
     * runtime, so its value cannot come from the rendered markup.
     */
    private function addRelation(Product $product, Product $related, string $typeCode): void
    {
        $this->client->request('POST', '/admin/products/association/add', [
            'product_id' => (int) $product->getId(),
            'type_code' => $typeCode,
            'associated_product_id' => (int) $related->getId(),
            'current_tab' => 'related',
            '_token' => $this->relatedTabToken($product),
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
    }

    private function removeRelation(Product $product, Product $related, string $typeCode): void
    {
        $this->client->request('POST', '/admin/products/association/delete', [
            'product_id' => (int) $product->getId(),
            'type_code' => $typeCode,
            'associated_product_id' => (int) $related->getId(),
            'current_tab' => 'related',
            '_token' => $this->relatedTabToken($product),
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
    }

    private function relatedTabToken(Product $product): string
    {
        $crawler = $this->client->request('GET', '/admin/products/related/tab?product_id='.$product->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $token = $crawler->filter('input[name="_token"]')->first();
        self::assertGreaterThan(0, $token->count(), 'Every block form carries its CSRF token.');

        return (string) $token->attr('value');
    }
}
