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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Product as ProductResource;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The transformer walks a resource graph and prunes a branch it has already
 * visited, or one below the maximum depth: the resource is still built, but
 * without its relations. The Propel getter was called before the loop reached
 * that decision, so the rows were read from the database and dropped on the
 * next statement.
 */
final class TransformerPrunedRelationQueryCountTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private const RELATION_TABLES = ['product_sale_elements', 'feature_product', 'product_category'];

    public function testAPrunedBranchReadsNoneOfTheRelationsItDropsAnyway(): void
    {
        $product = $this->productWithRelations();

        $statements = $this->recordSqlQueries(function () use ($product): void {
            $this->transform($this->freshModel($product), withRelation: false);
        });

        $reads = [];

        foreach (self::RELATION_TABLES as $table) {
            $reads[$table] = self::countSqlQueriesSelectingFrom($statements, $table);
        }

        self::assertSame(
            array_fill_keys(self::RELATION_TABLES, 0),
            $reads,
            'A pruned branch returns none of these relations, so it must not read any.',
        );
    }

    /**
     * The same guard must not starve a branch that is walked: pruning too much
     * would answer a product without its sale elements.
     */
    public function testAWalkedBranchStillReadsThem(): void
    {
        $product = $this->productWithRelations();

        $resource = $this->transform($this->freshModel($product), withRelation: true);

        self::assertNotEmpty($resource->getProductSaleElements());
        self::assertNotEmpty($resource->getFeatureProducts());
    }

    private function transform(Product $model, bool $withRelation): ProductResource
    {
        /** @var ProductResource $resource */
        $resource = $this->getService(ApiResourcePropelTransformerService::class)->modelToResource(
            resourceClass: ProductResource::class,
            propelModel: $model,
            context: ['groups' => [ProductResource::GROUP_FRONT_READ, ProductResource::GROUP_FRONT_READ_SINGLE]],
            withRelation: $withRelation,
        );

        return $resource;
    }

    /**
     * A model carrying its relations already would measure Propel's own cache,
     * not the transformer.
     */
    private function freshModel(Product $product): Product
    {
        $model = ProductQuery::create()->findPk($product->getId(), $this->getPropelConnection());

        self::assertInstanceOf(Product::class, $model);

        return $model;
    }

    private function productWithRelations(): Product
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $feature = $factory->feature(['title' => 'Colour']);
        $featureProduct = new FeatureProduct();
        $featureProduct->setProductId($product->getId());
        $featureProduct->setFeatureId($feature->getId());
        $featureProduct->setFeatureAvId($factory->featureAv($feature, ['title' => 'Blue'])->getId());
        $featureProduct->save($connection);

        return $product;
    }
}
