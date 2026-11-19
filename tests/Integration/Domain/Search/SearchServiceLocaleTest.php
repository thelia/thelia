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

namespace Thelia\Tests\Integration\Domain\Search;

use Psr\Log\NullLogger;
use Thelia\Domain\Search\Contract\SearchScope;
use Thelia\Domain\Search\Document\SearchDocument;
use Thelia\Domain\Search\Document\SearchQuery;
use Thelia\Domain\Search\Provider\ProductDocumentProvider;
use Thelia\Domain\Search\SearchDocumentProviders;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Domain\Search\SearchLocales;
use Thelia\Domain\Search\SearchService;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Search\InMemorySearchEngine;

final class SearchServiceLocaleTest extends IntegrationTestCase
{
    public function testALocaleTheEngineDoesNotIndexIsLeftToTheDatabase(): void
    {
        $fixtures = $this->createFixtureFactory();
        $product = $fixtures->product($fixtures->category(), $fixtures->taxRule(), $fixtures->currency(), ['title' => 'Lamp']);
        $engine = new InMemorySearchEngine();
        $engine->index([new SearchDocument('product', $product->getId(), 'en_US', SearchScope::Shop, true, ['title' => 'Lamp'])]);
        $search = new SearchService(new SearchEngineRegistry([$engine]), new SearchDocumentProviders([new ProductDocumentProvider()]), [], [], new NullLogger(), locales: new SearchLocales());

        self::assertSame([$product->getId()], $search->search(new SearchQuery('lamp', 'en_US', ['product']))?->idsOf('product'));
        self::assertNull($search->search(new SearchQuery('lamp', 'xx_XX', ['product'])));
        self::assertSame('en_US', $engine->lastQuery?->locale);
    }
}
