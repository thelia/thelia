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

use Propel\Runtime\Propel;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\OrderProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The GTIN, the manufacturer part number and the manufacturer brand of a combination on
 * the admin API: the same check as the back office, the duplicate reported, the codes
 * searchable.
 */
final class ProductIdentifiersApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testAValidGtinIsSavedWithoutItsSpaces(): void
    {
        $combination = $this->combination();

        $response = $this->patch($combination, ['eanCode' => '4006381 333931']);

        self::assertJsonResponseSuccessful($response);
        self::assertSame('4006381333931', $this->reloaded($combination)->getEanCode());
    }

    public function testAWrongCheckDigitIsRefusedWithAViolationOnTheField(): void
    {
        $combination = $this->combination(['eanCode' => '4006381333931']);

        $response = $this->patch($combination, ['eanCode' => '4006381333932', 'quantity' => 77]);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $violation = $this->violationOn('eanCode', $response);
        self::assertStringContainsString('check digit', $violation['message']);
        self::assertSame('4006381333931', $this->reloaded($combination)->getEanCode());
    }

    public function testACodeOfAnotherLengthIsRefusedWithTheLengthsAccepted(): void
    {
        $response = $this->patch($this->combination(), ['eanCode' => '03600029145']);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('8, 12, 13 or 14', $this->violationOn('eanCode', $response)['message']);
    }

    public function testAnInvalidCodeSentInsideItsProductIsRefusedToo(): void
    {
        $product = $this->product();
        $combination = $this->factory->productSaleElement($product, ['ref' => 'PSE-NESTED-GTIN']);

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'i18ns' => ['en_US' => ['title' => 'Product', 'locale' => 'en_US']],
            'productSaleElements' => [
                ['id' => $combination->getId(), 'ref' => 'PSE-NESTED-GTIN', 'quantity' => 1, 'eanCode' => '4006381333932'],
            ],
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull($this->reloaded($combination)->getEanCode());
    }

    public function testAStoredInvalidCodeSentBackUnchangedDoesNotBlockTheEdit(): void
    {
        $combination = $this->combination();
        $this->storeRawCode($combination, '1234567890123');

        $response = $this->patch($combination, ['eanCode' => '1234567890123', 'quantity' => 31]);

        self::assertJsonResponseSuccessful($response);
        $stored = $this->reloaded($combination);
        self::assertEqualsWithDelta(31, $stored->getQuantity(), 0.001);
        self::assertSame('1234567890123', $stored->getEanCode());
    }

    public function testThePartNumberAndTheManufacturerBrandAreWrittenAndRead(): void
    {
        $brand = $this->factory->brand();
        $combination = $this->combination();
        $token = $this->authenticateAsAdmin();

        self::assertJsonResponseSuccessful($this->patch($combination, [
            'mpn' => 'SM-G991B',
            'manufacturerBrand' => '/api/admin/brands/'.$brand->getId(),
        ], $token));

        $stored = $this->reloaded($combination);
        self::assertSame('SM-G991B', $stored->getMpn());
        self::assertSame($brand->getId(), $stored->getManufacturerBrandId());

        $read = self::decodeJson($this->jsonRequest('GET', '/api/admin/product_sale_elements/'.$combination->getId(), token: $token));
        self::assertSame('SM-G991B', $read['mpn']);
        self::assertStringEndsWith('/brands/'.$brand->getId(), \is_array($read['manufacturerBrand']) ? $read['manufacturerBrand']['@id'] : $read['manufacturerBrand']);
    }

    public function testAPartNumberLongerThanTheColumnIsRefused(): void
    {
        $response = $this->patch($this->combination(), ['mpn' => str_repeat('A', 256)]);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $this->violationOn('mpn', $response);
    }

    public function testADuplicateIsSavedAndReportedWithTheOtherCombination(): void
    {
        $first = $this->combination(['eanCode' => '9780306406157']);
        $second = $this->combination();

        $response = $this->patch($second, ['eanCode' => '9780306406157']);

        self::assertJsonResponseSuccessful($response);
        self::assertSame([$first->getId()], self::decodeJson($response)['gtinSharedWith']);
        self::assertSame('9780306406157', $this->reloaded($second)->getEanCode());
    }

    public function testAPageOfCombinationsReportsTheSharersOfEachInOneLookup(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();
        $first = $this->factory->productSaleElement($product, ['eanCode' => '9780306406157']);
        $second = $this->factory->productSaleElement($product, ['eanCode' => '9780306406157']);
        $alone = $this->factory->productSaleElement($product, ['eanCode' => '96385074']);
        $sharersById = [];

        $statements = $this->recordSqlQueries(function () use ($token, $product, &$sharersById): void {
            $response = $this->jsonRequest('GET', '/api/admin/product_sale_elements?'.http_build_query(['product.id' => $product->getId(), 'itemsPerPage' => 50]), token: $token);
            self::assertJsonResponseSuccessful($response);
            $payload = self::decodeJson($response);

            foreach ($payload['hydra:member'] ?? $payload['member'] ?? [] as $member) {
                $sharersById[(int) $member['id']] = $member['gtinSharedWith'] ?? null;
            }
        });

        self::assertSame([$second->getId()], $sharersById[$first->getId()] ?? null);
        self::assertSame([$first->getId()], $sharersById[$second->getId()] ?? null);
        self::assertSame([], $sharersById[$alone->getId()] ?? null);

        $lookups = array_filter(
            $statements,
            static fn (string $statement): bool => str_contains($statement, 'FROM `product_sale_elements`') && str_contains($statement, 'ean_code` IN'),
        );
        self::assertCount(1, $lookups, 'The codes of a page are looked up once, not once per combination.');
    }

    public function testACombinationIsFoundByItsExactGtinOrPartNumber(): void
    {
        $token = $this->authenticateAsAdmin();
        $wanted = $this->combination(['eanCode' => '10012345600019', 'mpn' => 'MPN-SEARCHED-'.uniqid()]);
        $this->combination(['eanCode' => '96385074']);

        foreach (['eanCode' => '10012345600019', 'mpn' => $wanted->getMpn()] as $filter => $value) {
            $ids = $this->idsOf($this->jsonRequest('GET', '/api/admin/product_sale_elements?'.http_build_query([$filter => $value]), token: $token));
            self::assertSame([$wanted->getId()], $ids, $filter);
        }

        $partial = $this->idsOf($this->jsonRequest('GET', '/api/admin/product_sale_elements?eanCode=1001234560', token: $token));
        self::assertSame([], $partial, 'A partial code finds nothing: the filter is exact.');

        $products = $this->idsOf($this->jsonRequest('GET', '/api/admin/products?'.http_build_query(['productSaleElements.eanCode' => '10012345600019']), token: $token));
        self::assertSame([$wanted->getProductId()], $products);
    }

    public function testTheOrderLineGivesThePartNumberSold(): void
    {
        $combination = $this->combination();
        $order = $this->factory->order();
        $orderProduct = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef($combination->getProduct()->getRef())
            ->setProductSaleElementsRef($combination->getRef())
            ->setProductSaleElementsId($combination->getId())
            ->setTitle('Sold product')
            ->setQuantity(1.0)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setEanCode('4006381333931')
            ->setMpn('MPN-SOLD');
        $orderProduct->save($this->getPropelConnection());

        $read = self::decodeJson($this->jsonRequest('GET', '/api/admin/order_products/'.$orderProduct->getId(), token: $this->authenticateAsAdmin()));

        self::assertSame('MPN-SOLD', $read['mpn']);
        self::assertSame('4006381333931', $read['eanCode']);
    }

    public function testWritingWithoutATokenIsRefused(): void
    {
        $combination = $this->combination();

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), ['mpn' => 'NO-TOKEN'], null, 'merge-patch+json');

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($this->reloaded($combination)->getMpn());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function combination(array $overrides = []): ProductSaleElements
    {
        return $this->factory->productSaleElement($this->product(), $overrides);
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patch(ProductSaleElements $combination, array $payload, ?string $token = null): \Symfony\Component\HttpFoundation\Response
    {
        return $this->jsonRequest(
            'PATCH',
            '/api/admin/product_sale_elements/'.$combination->getId(),
            $payload,
            $token ?? $this->authenticateAsAdmin(),
            'merge-patch+json',
        );
    }

    /**
     * @return array{propertyPath: string, message: string}
     */
    private function violationOn(string $property, \Symfony\Component\HttpFoundation\Response $response): array
    {
        foreach (self::decodeJson($response)['violations'] ?? [] as $violation) {
            if ($violation['propertyPath'] === $property) {
                return $violation;
            }
        }

        self::fail('No violation on '.$property.': '.$response->getContent());
    }

    /**
     * @return list<int>
     */
    private function idsOf(\Symfony\Component\HttpFoundation\Response $response): array
    {
        self::assertJsonResponseSuccessful($response);
        $payload = self::decodeJson($response);

        return array_map(static fn (array $member): int => (int) $member['id'], $payload['hydra:member'] ?? $payload['member'] ?? []);
    }

    private function storeRawCode(ProductSaleElements $combination, string $code): void
    {
        $statement = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME)
            ->prepare('UPDATE product_sale_elements SET ean_code = :code WHERE id = :id');
        $statement->execute(['code' => $code, 'id' => $combination->getId()]);
        ProductSaleElementsTableMap::clearInstancePool();
    }

    private function reloaded(ProductSaleElements $combination): ProductSaleElements
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return ProductSaleElementsQuery::create()->findPk($combination->getId())
            ?? throw new \RuntimeException('The combination is gone.');
    }
}
