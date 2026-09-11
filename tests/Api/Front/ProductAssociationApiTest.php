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

use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Map\AccessoryTableMap;
use Thelia\Model\Map\ProductAssociationTypeI18nTableMap;
use Thelia\Model\Map\ProductAssociationTypeTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociationType;
use Thelia\Model\ProductAssociationTypeQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;

/**
 * The front reads one block of a product sheet in a single call, and reads only
 * what the shop offers.
 *
 * A relation carries the id of the product it points at, so a block that ignored
 * visibility would hand out the products a shop has taken offline. The same goes
 * for a type the merchant has hidden: its block is no longer offered, so its
 * relations are not either.
 */
final class ProductAssociationApiTest extends ApiTestCase
{
    private Currency $currency;
    private Category $category;
    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = $factory->currency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
    }

    public function testABlockIsReadInOneCallFilteredByProductAndType(): void
    {
        $product = $this->product();
        $first = $this->product();
        $second = $this->product();

        $this->relate($product, $first, ProductAssociationType::CODE_CROSS_SELLING);
        $this->relate($product, $second, ProductAssociationType::CODE_CROSS_SELLING);
        $this->relate($product, $this->product(), ProductAssociationType::CODE_UP_SELLING);

        $payload = $this->readJson(\sprintf(
            '/api/front/product_associations?product.id=%d&type.code=%s&order[position]=asc',
            $product->getId(),
            ProductAssociationType::CODE_CROSS_SELLING,
        ));

        self::assertSame(2, $payload['hydra:totalItems'], 'The block holds the relations of its own type only.');
        self::assertSame(
            [$first->getId(), $second->getId()],
            array_map(static fn (array $row): int => (int) $row['associatedProduct']['id'], $payload['hydra:member']),
            'The relations come back in the order the merchant gave them.',
        );
    }

    public function testTheBlockCarriesTheTranslatedTitleOfItsType(): void
    {
        $product = $this->product();
        $this->relate($product, $this->product(), ProductAssociationType::CODE_ACCESSORY);

        $payload = $this->readJson('/api/front/product_associations?product.id='.$product->getId());

        $type = $payload['hydra:member'][0]['type'] ?? [];

        self::assertSame(ProductAssociationType::CODE_ACCESSORY, $type['code'] ?? null);
        self::assertNotEmpty(
            $type['i18ns'] ?? [],
            'A theme titles the block with the translated wording of the type, so the read has to carry it.',
        );
    }

    public function testARelationTowardsAnOfflineProductIsNotOffered(): void
    {
        $product = $this->product();
        $offline = $this->product();
        $offline->setVisible(0)->save();

        $this->relate($product, $offline, ProductAssociationType::CODE_ACCESSORY);

        $payload = $this->readJson('/api/front/product_associations?product.id='.$product->getId());

        self::assertSame(0, $payload['hydra:totalItems'], 'A product taken offline is not handed out by the relations of an online one.');
    }

    public function testTheRelationsOfAHiddenTypeAreNotOfferedEither(): void
    {
        $product = $this->product();
        $this->relate($product, $this->product(), ProductAssociationType::CODE_UP_SELLING);

        $this->type(ProductAssociationType::CODE_UP_SELLING)->setVisible(0)->save();

        $payload = $this->readJson('/api/front/product_associations?product.id='.$product->getId());

        self::assertSame(0, $payload['hydra:totalItems'], 'Hiding a type stops offering its block, relations included.');
    }

    public function testAnOfflineProductIsNotReachableByTheIdOfItsRelationEither(): void
    {
        $product = $this->product();
        $offline = $this->product();
        $offline->setVisible(0)->save();

        $this->relate($product, $offline, ProductAssociationType::CODE_ACCESSORY);

        $relations = $this->facade()->getAssociations((int) $product->getId(), ProductAssociationType::CODE_ACCESSORY);
        $relationId = (int) $relations[0]->getId();

        $response = $this->jsonRequest('GET', '/api/front/product_associations/'.$relationId);

        self::assertSame(404, $response->getStatusCode(), 'A relation out of reach answers 404, not an empty-looking 200.');
    }

    public function testTheFrontRefusesToWriteARelation(): void
    {
        $product = $this->product();
        $associated = $this->product();

        $response = $this->jsonRequest('POST', '/api/front/product_associations', [
            'product' => '/api/front/products/'.$product->getId(),
            'associatedProduct' => '/api/front/products/'.$associated->getId(),
            'type' => '/api/front/product_association_types/'.$this->type(ProductAssociationType::CODE_ACCESSORY)->getId(),
        ]);

        self::assertSame(405, $response->getStatusCode(), 'The front endpoints are read-only.');
    }

    public function testTheBlockCarriesEnoughOfTheLinkedProductToRenderACard(): void
    {
        $product = $this->product();
        $associated = $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['title' => 'A product a card names'],
        );

        $this->relate($product, $associated, ProductAssociationType::CODE_ACCESSORY);

        $payload = $this->readJson('/api/front/product_associations?product.id='.$product->getId());
        $linked = $payload['hydra:member'][0]['associatedProduct'] ?? null;

        self::assertIsArray($linked, 'A card cannot be rendered from a bare IRI: the linked product is served with the relation.');
        self::assertSame((int) $associated->getId(), $linked['id']);
        self::assertContains(
            'A product a card names',
            array_column($linked['i18ns'] ?? [], 'title'),
            'The card is titled with the wording of the product it links to.',
        );
        self::assertArrayHasKey('publicUrl', $linked, 'The card links to the sheet of the product.');

        $saleElements = $linked['productSaleElements'] ?? [];

        self::assertNotEmpty($saleElements, 'The card shows an availability, which the sale elements carry.');
        self::assertNotEmpty(
            array_filter($saleElements, static fn (array $saleElement): bool => true === ($saleElement['isDefault'] ?? false)),
            'The default sale element is told apart from the others, which is what a card reads.',
        );
    }

    public function testALongerBlockDoesNotCostMoreQueries(): void
    {
        // The first read of the process fills memos that outlive the request —
        // the active languages, the modules, the rewritten urls — and pays a
        // query for each. Measuring it against a later one compares a cold run
        // with a warm one and reads a difference that has nothing to do with the
        // length of the block.
        $this->queriesSpentReadingABlockOf(1);

        $shortBlock = $this->queriesSpentReadingABlockOf(2);
        $longBlock = $this->queriesSpentReadingABlockOf(6);

        self::assertSame(
            $shortBlock,
            $longBlock,
            'Reading a block costs the same whatever its length: the products it points at are read for the whole page, not one by one.',
        );
    }

    private function readJson(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri);

        self::assertJsonResponseSuccessful($response);

        return self::decodeJson($response);
    }

    private function relate(Product $product, Product $associated, string $typeCode): void
    {
        $this->facade()->addAssociation((int) $product->getId(), (int) $associated->getId(), $typeCode);
    }

    private function type(string $code): ProductAssociationType
    {
        $type = ProductAssociationTypeQuery::create()->findOneByCode($code);

        self::assertInstanceOf(ProductAssociationType::class, $type, \sprintf('The install seeds the "%s" type.', $code));

        return $type;
    }

    private function product(): Product
    {
        return $this->createFixtureFactory()->product($this->category, $this->taxRule, $this->currency);
    }

    private function queriesSpentReadingABlockOf(int $relations): int
    {
        $product = $this->product();

        for ($written = 0; $written < $relations; ++$written) {
            $this->relate($product, $this->product(), ProductAssociationType::CODE_ACCESSORY);
        }

        $this->forgetWhatTheFixturesHoldInMemory();

        $connection = Propel::getServiceContainer()->getConnection();
        $connection->useDebug(true);
        $before = $connection->getQueryCount();

        $this->readJson('/api/front/product_associations?product.id='.$product->getId());

        return $connection->getQueryCount() - $before;
    }

    /**
     * The fixtures are built in the process that serves the request, so every row
     * they wrote answers out of the Propel instance pool and the read never
     * reaches the database for it. A shop has no such thing, and a measurement
     * taken without this proves nothing: it reads the same figure whatever the
     * length of the block.
     */
    private function forgetWhatTheFixturesHoldInMemory(): void
    {
        AccessoryTableMap::clearInstancePool();
        ProductTableMap::clearInstancePool();
        ProductI18nTableMap::clearInstancePool();
        ProductSaleElementsTableMap::clearInstancePool();
        ProductAssociationTypeTableMap::clearInstancePool();
        ProductAssociationTypeI18nTableMap::clearInstancePool();
    }

    private function facade(): ProductFacade
    {
        return $this->getService(ProductFacade::class);
    }
}
