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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Test\ApiTestCase;

/**
 * A category asked with a depth lists the products of its whole branch, whatever other filter
 * the request carries: a front listing always sends visible=true, and a ref or a brand narrows
 * the branch, it never replaces it.
 *
 * Catalogue:
 *
 *   root            ROOT
 *     shoes         SHOES, SHOES-HIDDEN (not visible)
 *       sneakers    SNEAKERS
 *   other root      OTHER
 */
final class ProductDepthFilterApiTest extends ApiTestCase
{
    private int $rootId;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $root = $factory->category();
        $shoes = $factory->category(['parent' => $root->getId()]);
        $sneakers = $factory->category(['parent' => $shoes->getId()]);
        $other = $factory->category();
        $this->rootId = $root->getId();

        $factory->product($root, $taxRule, $currency, ['ref' => 'DEPTH-ROOT']);
        $factory->product($shoes, $taxRule, $currency, ['ref' => 'DEPTH-SHOES']);
        $factory->product($shoes, $taxRule, $currency, ['ref' => 'DEPTH-SHOES-HIDDEN', 'visible' => 0]);
        $factory->product($sneakers, $taxRule, $currency, ['ref' => 'DEPTH-SNEAKERS']);
        $factory->product($other, $taxRule, $currency, ['ref' => 'DEPTH-OTHER']);
    }

    public function testADepthListsTheWholeBranch(): void
    {
        self::assertSame(
            ['DEPTH-ROOT', 'DEPTH-SHOES', 'DEPTH-SHOES-HIDDEN', 'DEPTH-SNEAKERS'],
            $this->references('productCategories.category.id='.$this->rootId.'&depth=10'),
        );
    }

    public function testADepthStillListsTheWholeBranchOfVisibleProducts(): void
    {
        self::assertSame(
            ['DEPTH-ROOT', 'DEPTH-SHOES', 'DEPTH-SNEAKERS'],
            $this->references('productCategories.category.id='.$this->rootId.'&depth=10&visible=true'),
        );
    }

    public function testAnotherFilterNarrowsTheBranchInsteadOfReplacingIt(): void
    {
        self::assertSame(
            ['DEPTH-SNEAKERS'],
            $this->references('productCategories.category.id='.$this->rootId.'&depth=10&ref=DEPTH-SNEAKERS'),
        );
        self::assertSame(
            [],
            $this->references('productCategories.category.id='.$this->rootId.'&depth=10&ref=DEPTH-OTHER'),
            'a product outside the branch stays out',
        );
    }

    public function testADepthOfOneStopsAtTheChildren(): void
    {
        self::assertSame(
            ['DEPTH-ROOT', 'DEPTH-SHOES'],
            $this->references('productCategories.category.id='.$this->rootId.'&depth=1&visible=true'),
        );
    }

    /**
     * @return list<string>
     */
    private function references(string $query): array
    {
        $response = $this->jsonRequest('GET', '/api/front/products?'.$query.'&order[ref]=asc&itemsPerPage=50');
        self::assertJsonResponseSuccessful($response);

        return array_map(
            static fn (array $product): string => (string) $product['ref'],
            self::members($response),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function members(Response $response): array
    {
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $payload['hydra:member'] ?? [];
    }
}
