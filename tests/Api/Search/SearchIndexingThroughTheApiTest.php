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

namespace Thelia\Tests\Api\Search;

use Thelia\Domain\Search\Contract\SearchScope;
use Thelia\Domain\Search\Document\SearchDocument;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Search\InMemorySearchEngine;

final class SearchIndexingThroughTheApiTest extends ApiTestCase
{
    private InMemorySearchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new InMemorySearchEngine();
        static::getContainer()->set(SearchEngineRegistry::class, new SearchEngineRegistry([$this->engine]));
    }

    public function testAProductRenamedThroughTheAdminApiIsFoundUnderItsNewTitle(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['title' => 'Old name']);

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), ['i18ns' => ['en_US' => ['title' => 'Written by the API', 'locale' => 'en_US']]], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('Written by the API', $this->engine->get('product', $product->getId(), 'en_US')?->fields['title']);
    }

    public function testAProductDeletedThroughTheAdminApiLeavesTheIndex(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['title' => 'Doomed']);
        $id = $product->getId();
        $this->engine->index([new SearchDocument('product', $id, 'en_US', SearchScope::Shop, true, ['title' => 'Doomed'])]);

        $response = $this->jsonRequest('DELETE', '/api/admin/products/'.$id, [], $this->authenticateAsAdmin());

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertFalse($this->engine->has('product', $id, 'en_US'));
    }
}
