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

use Thelia\Model\AttributeCombination;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A product carries features, attribute combinations and associated contents
 * that only the single read returns. Walking them on a collection buys rows the
 * serializer throws away, and the bill grows with the page: the home page of the
 * demo shop paid 96 reads of attribute_combination for a payload naming none.
 */
final class ProductCollectionRelationQueryCountTest extends ApiTestCase
{
    use RecordsSqlQueries;

    public function testTheCollectionDoesNotReadTheRelationsItDoesNotReturn(): void
    {
        $product = $this->productWithEveryOptionalRelation();

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload): void {
            $payload = $this->readJson('/api/front/products');
        });

        $member = $this->member($payload, $product->getId());
        self::assertArrayNotHasKey('featureProducts', $member);
        self::assertArrayNotHasKey('productAssociatedContents', $member);
        self::assertArrayHasKey('productSaleElements', $member);

        self::assertSame(
            0,
            self::countSqlQueriesSelectingFrom($statements, 'feature_product'),
            'The collection returns no feature, so it must not read any.',
        );
        self::assertSame(
            0,
            self::countSqlQueriesSelectingFrom($statements, 'attribute_combination'),
            'The collection returns no attribute combination, so it must not read any.',
        );
    }

    /**
     * The same relations belong to the single read, where the guard must let them
     * through: skipping them would answer an incomplete product.
     */
    public function testTheSingleReadStillReturnsThem(): void
    {
        $product = $this->productWithEveryOptionalRelation();

        $payload = $this->readJson('/api/front/products/'.$product->getId());

        self::assertNotEmpty($payload['featureProducts'] ?? []);
        self::assertNotEmpty($payload['productSaleElements'][0]['attributeCombinations'] ?? []);
    }

    private function productWithEveryOptionalRelation(): Product
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

        $attribute = $factory->attribute(['title' => 'Size']);
        $combination = new AttributeCombination();
        $combination->setProductSaleElementsId($product->getDefaultSaleElements()->getId());
        $combination->setAttributeId($attribute->getId());
        $combination->setAttributeAvId($factory->attributeAv($attribute, ['title' => 'Large'])->getId());
        $combination->save($connection);

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function member(array $payload, ?int $productId): array
    {
        foreach ($payload['hydra:member'] ?? [] as $member) {
            if (($member['id'] ?? null) === $productId) {
                return $member;
            }
        }

        self::fail('The collection must return the product under test, otherwise nothing is measured.');
    }
}
