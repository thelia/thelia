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

use Thelia\Test\ApiTestCase;

/**
 * Tests for API Platform filters (SearchFilter, BooleanFilter, OrderFilter).
 */
final class FiltersApiTest extends ApiTestCase
{
    public function testSearchFilterByProductRef(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $factory->product($category, $taxRule, $currency, ['ref' => 'SEARCH-ALPHA']);
        $factory->product($category, $taxRule, $currency, ['ref' => 'SEARCH-BETA']);

        $response = $this->jsonRequest('GET', '/api/admin/products?ref=SEARCH-ALPHA', token: $token);
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
    }

    public function testBooleanFilterByVisible(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $factory->product($category, $taxRule, $currency, ['visible' => 1]);
        $factory->product($category, $taxRule, $currency, ['visible' => 0]);

        $response = $this->jsonRequest('GET', '/api/admin/products?visible=true', token: $token);
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
    }

    public function testOrderFilterByRef(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $factory->product($category, $taxRule, $currency, ['ref' => 'AAA-FIRST']);
        $factory->product($category, $taxRule, $currency, ['ref' => 'ZZZ-LAST']);

        $response = $this->jsonRequest('GET', '/api/admin/products?order[ref]=asc', token: $token);
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        $refs = array_column($data['hydra:member'], 'ref');
        self::assertSame('AAA-FIRST', $refs[0]);
    }

    public function testPaginationItemsPerPage(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        for ($i = 0; $i < 5; ++$i) {
            $factory->product($category, $taxRule, $currency);
        }

        $response = $this->jsonRequest('GET', '/api/admin/products?itemsPerPage=2', token: $token);
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        self::assertCount(2, $data['hydra:member']);
        self::assertSame(5, $data['hydra:totalItems']);
    }

    public function testCategorySearchFilter(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();

        $factory->category();
        $factory->category();

        // Search categories with visible=true.
        $response = $this->jsonRequest('GET', '/api/admin/categories?visible=true', token: $token);
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        self::assertGreaterThanOrEqual(2, $data['hydra:totalItems']);
    }
}
