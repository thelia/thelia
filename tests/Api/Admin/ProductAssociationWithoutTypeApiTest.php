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

use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;

/**
 * A relation written without the type it carries is a payload the merchant can
 * fix: it is refused, it does not crash.
 */
final class ProductAssociationWithoutTypeApiTest extends ApiTestCase
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

    public function testWritingARelationWithoutATypeIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();
        $product = $this->product();
        $associated = $this->product();

        try {
            $response = $this->jsonRequest('POST', '/api/admin/product_associations', [
                'product' => '/api/admin/products/'.$product->getId(),
                'associatedProduct' => '/api/admin/products/'.$associated->getId(),
            ], $token);
        } catch (\Throwable $crash) {
            self::fail(
                'A relation payload with no type must be refused, not crash the write: '
                .$crash::class.' — '.$crash->getMessage(),
            );
        }

        self::assertSame(
            422,
            $response->getStatusCode(),
            'A relation payload with no type is refused as unprocessable, not answered by a 500: '
            .substr((string) $response->getContent(), 0, 300),
        );
    }

    private function product(): Product
    {
        return $this->createFixtureFactory()->product($this->category, $this->taxRule, $this->currency);
    }
}
