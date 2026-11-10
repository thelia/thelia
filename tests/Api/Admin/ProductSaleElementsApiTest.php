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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;

final class ProductSaleElementsApiTest extends ApiTestCase
{
    /**
     * A sale element sent inside its product with no id used to become a new
     * row, and the previous one was deleted with its attribute combinations
     * and the customer family prices set on it. An ERP holds its own
     * references and sends the same one on every synchronisation, so the row
     * it means is the one carrying that reference.
     */
    public function testNestedSaleElementWithoutAnIdUpdatesTheRowHoldingItsReference(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'PSE-BY-REF', 'quantity' => 10]);

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'i18ns' => ['en_US' => ['title' => 'Product', 'locale' => 'en_US']],
            'productSaleElements' => [
                ['ref' => 'PSE-BY-REF', 'quantity' => 42],
            ],
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertNotNull($reloaded, 'the sale element holding the reference was replaced instead of updated');
        self::assertEqualsWithDelta(42, $reloaded->getQuantity(), 0.001);
    }

    /**
     * The reference has no unique index, so two rows may hold the same one.
     * Picking either would be a guess, and the previous behaviour - a new row -
     * is the safe answer.
     */
    public function testAnAmbiguousReferenceCreatesARowRatherThanGuessing(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $factory->productSaleElement($product, ['ref' => 'PSE-TWICE']);
        $factory->productSaleElement($product, ['ref' => 'PSE-TWICE']);

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'i18ns' => ['en_US' => ['title' => 'Product', 'locale' => 'en_US']],
            'productSaleElements' => [
                ['ref' => 'PSE-TWICE', 'quantity' => 7],
            ],
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);

        $rows = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByRef('PSE-TWICE')
            ->count();
        self::assertSame(1, $rows, 'an ambiguous reference must not be resolved to one of the rows');
    }

    /**
     * A PUT replaces the resource, so a payload carrying only the stock leaves
     * every other writable property out. Those properties map to NOT NULL
     * columns, and writing null into them used to reach the database and come
     * back as a 500 carrying the failed SQL statement.
     */
    public function testPartialPutDoesNotEndAsAServerError(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'PSE-PARTIAL-PUT']);

        $response = $this->jsonRequest('PUT', '/api/admin/product_sale_elements/'.$pse->getId(), [
            'quantity' => 42,
            'weight' => 2.5,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), $response->getContent());

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertNotNull($reloaded);
        self::assertSame($product->getId(), $reloaded->getProductId());
    }

    /**
     * The same payload sent as a merge patch is the documented way to update a
     * single property, and it must keep every other column untouched.
     */
    public function testPartialPatchUpdatesOnlyTheSubmittedProperties(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'PSE-PARTIAL-PATCH']);
        $position = $pse->getPosition();

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$pse->getId(), [
            'quantity' => 42,
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertEqualsWithDelta(42, $reloaded->getQuantity(), 0.001);
        self::assertSame($product->getId(), $reloaded->getProductId());
        self::assertSame($position, $reloaded->getPosition());
        self::assertSame('PSE-PARTIAL-PATCH', $reloaded->getRef());
    }

    public function testAnAdministratorWritesTheQuantityRulesOfACombination(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'PSE-RULES']);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$pse->getId(), [
            'quantity' => 37.5,
            'minQuantity' => 1,
            'quantityStep' => 0.25,
            'quantityDecimals' => 2,
            'unitQuantity' => 1,
            'referenceUnitQuantity' => 1,
            'unitCode' => 'kg',
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        $json = json_decode((string) $response->getContent(), true);
        self::assertSame(37.5, $json['quantity']);
        self::assertEquals(0.25, $json['quantityStep']);
        self::assertSame(2, $json['quantityDecimals']);
        self::assertSame('kg', $json['unitCode']);

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertSame('0.250000', $reloaded->getQuantityStep());
        self::assertSame(2, $reloaded->getQuantityDecimals());
        self::assertSame('kg', $reloaded->getUnitCode());
        self::assertEqualsWithDelta(37.5, $reloaded->getQuantity(), 0.0001);
    }

    /**
     * A partial write carries only what it changes: the rules it does not post keep their value
     * in the database, they are not brought back to 1 (a step of 0.5 would become a step of 1).
     */
    public function testAPartialWriteOfOneRuleFieldLeavesTheOtherRulesAsTheyAre(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'PSE-PARTIAL-RULES']);
        $pse->setMinQuantity('0.500000')->setQuantityStep('0.500000')->setQuantityDecimals(1)->setUnitQuantity('2.000000')->setReferenceUnitQuantity('1.000000')->setUnitCode('kg')->save();

        foreach ([['quantityDecimals' => 2], ['quantityDecimals' => 1], ['unitCode' => 'kg']] as $payload) {
            $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$pse->getId(), $payload, $token, 'merge-patch+json');

            self::assertJsonResponseSuccessful($response);

            ProductSaleElementsTableMap::clearInstancePool();
            $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
            self::assertSame('0.500000', $reloaded->getMinQuantity(), json_encode($payload).' moved the minimum.');
            self::assertSame('0.500000', $reloaded->getQuantityStep(), json_encode($payload).' moved the step.');
            self::assertSame('2.000000', $reloaded->getUnitQuantity(), json_encode($payload).' moved the unit quantity.');
            self::assertSame('1.000000', $reloaded->getReferenceUnitQuantity(), json_encode($payload).' moved the reference unit quantity.');
            self::assertSame('kg', $reloaded->getUnitCode());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function quantityRulesThatCannotDecideAQuantity(): iterable
    {
        yield 'a step of zero' => [['quantityStep' => 0], 'quantityStep'];
        yield 'a negative minimum' => [['minQuantity' => -1], 'minQuantity'];
        yield 'four decimals' => [['quantityDecimals' => 4], 'quantityDecimals'];
        yield 'negative decimals' => [['quantityDecimals' => -1], 'quantityDecimals'];
        yield 'a step finer than the decimals' => [['quantityStep' => 0.25, 'quantityDecimals' => 1], 'quantityStep'];
        yield 'a minimum finer than the decimals' => [['minQuantity' => 1.5], 'minQuantity'];
        yield 'a unit quantity of zero' => [['unitQuantity' => 0], 'unitQuantity'];
        yield 'a negative reference unit quantity' => [['referenceUnitQuantity' => -1], 'referenceUnitQuantity'];
        yield 'an unknown unit code' => [['unitCode' => 'furlong'], 'unitCode'];
        yield 'a minimum below a millionth' => [['minQuantity' => 0.0000001], 'minQuantity'];
        yield 'a step below a millionth' => [['quantityStep' => 0.0000001], 'quantityStep'];
        yield 'a unit quantity below a millionth' => [['unitQuantity' => 0.0000001], 'unitQuantity'];
        yield 'a reference unit quantity below a millionth' => [['referenceUnitQuantity' => 0.0000001], 'referenceUnitQuantity'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('quantityRulesThatCannotDecideAQuantity')]
    public function testAQuantityRuleThatCannotDecideAQuantityIsRefused(array $payload, string $property): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'PSE-BAD-RULES']);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$pse->getId(), $payload, $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $violations = json_decode((string) $response->getContent(), true)['violations'] ?? [];
        self::assertContains($property, array_column($violations, 'propertyPath'), (string) $response->getContent());

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertSame('1.000000', $reloaded->getQuantityStep());
        self::assertSame(0, $reloaded->getQuantityDecimals());
        self::assertNull($reloaded->getUnitCode());
    }
}
