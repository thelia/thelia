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

use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociationType;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;

/**
 * A private drop stays private on the new relation endpoints.
 *
 * A reserved operation with `hide_products` takes its products out of the
 * catalog of everybody it is not open to, and the rule is applied in the query
 * of /front/products and /front/product_sale_elements
 * (ReservedSaleVisibilityExtension). ProductAssociationVisibilityExtension
 * narrows the new /front/product_associations on `product.visible` and on the
 * visibility of the type only, so a relation pointing at a product of a private
 * drop is still offered — and the relation carries that product whole.
 */
final class ProductAssociationReservedSaleVisibilityApiTest extends ApiTestCase
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

    public function testTheProductOfAPrivateDropIsNotHandedOutByTheRelationsOfAnOnlineProduct(): void
    {
        $sheet = $this->catalogProduct();
        $reserved = $this->catalogProduct();
        $this->hiddenReservedSaleOn($reserved, $this->newCustomer());

        $this->facade()->addAssociation(
            (int) $sheet->getId(),
            (int) $reserved->getId(),
            ProductAssociationType::CODE_ACCESSORY,
        );

        $response = $this->jsonRequest('GET', '/api/front/product_associations?product.id='.$sheet->getId());
        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $offered = array_map(
            static fn (array $row): int => (int) ($row['associatedProduct']['id'] ?? 0),
            $payload['hydra:member'] ?? [],
        );

        self::assertNotContains(
            (int) $reserved->getId(),
            $offered,
            'A product hidden by a private drop is handed out by the relations of an online product: '
            .'the front relation endpoint is not narrowed by the reserved operation rule.',
        );
    }

    public function testTheRelationsOfAPrivateDropProductHandItOutFromTheOtherSideToo(): void
    {
        $reserved = $this->catalogProduct();
        $online = $this->catalogProduct();
        $this->hiddenReservedSaleOn($reserved, $this->newCustomer());

        $this->facade()->addAssociation(
            (int) $reserved->getId(),
            (int) $online->getId(),
            ProductAssociationType::CODE_ACCESSORY,
        );

        $response = $this->jsonRequest('GET', '/api/front/product_associations?product.id='.$reserved->getId());
        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(
            0,
            $payload['hydra:totalItems'] ?? -1,
            'The relations of a product hidden by a private drop are served to a visitor the drop is not open to, '
            .'and each of them carries that product whole.',
        );
    }

    private function catalogProduct(): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Private drop product'],
        );
    }

    private function hiddenReservedSaleOn(Product $product, Customer $customer): Sale
    {
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
            'active' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleProduct($sale, $product);
        $factory->saleCustomer($sale, $customer);
        $factory->saleOffsetCurrency($sale, $this->currency, 10.0);

        return $sale;
    }

    private function newCustomer(): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle());
    }

    private function facade(): ProductFacade
    {
        return $this->getService(ProductFacade::class);
    }
}
