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
use Thelia\Domain\Search\Document\SearchQuery;
use Thelia\Domain\Search\Provider\BrandDocumentProvider;
use Thelia\Domain\Search\Provider\CategoryDocumentProvider;
use Thelia\Domain\Search\Provider\ContentDocumentProvider;
use Thelia\Domain\Search\Provider\FolderDocumentProvider;
use Thelia\Domain\Search\Provider\ProductDocumentProvider;
use Thelia\Domain\Search\SearchDocumentProviders;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Domain\Search\SearchIndexer;
use Thelia\Domain\Search\SearchLocales;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Search\InMemorySearchEngine;
use Thelia\Tests\Support\Search\SpyDocumentProvider;

final class SearchIndexerTest extends IntegrationTestCase
{
    private const array LOCALES = ['en_US', 'fr_FR'];

    private InMemorySearchEngine $engine;
    private SearchIndexer $indexer;
    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new InMemorySearchEngine();
        $this->indexer = self::indexer($this->engine);
        $this->fixtures = $this->createFixtureFactory();
    }

    public static function indexer(InMemorySearchEngine $engine): SearchIndexer
    {
        return new SearchIndexer(
            new SearchEngineRegistry([$engine]),
            new SearchDocumentProviders([
                new ProductDocumentProvider(),
                new CategoryDocumentProvider(),
                new ContentDocumentProvider(),
                new FolderDocumentProvider(),
                new BrandDocumentProvider(),
            ]),
            new SearchLocales(),
            new NullLogger(),
        );
    }

    public function testAProductIsIndexedInEachLocaleWithItsOwnTextAndItsReferences(): void
    {
        $product = $this->product('Garden chair', ['ref' => 'CHAIR-42']);
        $product->setLocale('fr_FR')->setTitle('Chaise de jardin')->save();

        $this->indexer->synchronize('product', [$product->getId()], self::LOCALES);

        $english = $this->engine->get('product', $product->getId(), 'en_US');
        $french = $this->engine->get('product', $product->getId(), 'fr_FR');
        self::assertSame('Garden chair', $english?->fields['title']);
        self::assertSame('Chaise de jardin', $french?->fields['title']);
        self::assertSame('CHAIR-42', $english->fields['ref']);
        self::assertStringContainsString((string) $product->getDefaultSaleElements()->getRef(), $english->fields['references']);
        self::assertSame(10, $english->weightOf('title'));
        self::assertSame(SearchScope::Shop, $english->scope);
    }

    public function testALocaleWithoutATranslationOfItsOwnHasNoDocument(): void
    {
        $product = $this->product('Garden chair');

        $this->indexer->synchronize('product', [$product->getId()], self::LOCALES);

        self::assertTrue($this->engine->has('product', $product->getId(), 'en_US'));
        self::assertFalse($this->engine->has('product', $product->getId(), 'fr_FR'));
    }

    public function testAnInvisibleProductIsIndexedButNeverFoundByTheShop(): void
    {
        $product = $this->product('Hidden lamp', ['visible' => 0]);

        $this->indexer->synchronize('product', [$product->getId()], self::LOCALES);

        self::assertFalse($this->engine->get('product', $product->getId(), 'en_US')?->visible);
        self::assertSame([], $this->engine->search(new SearchQuery('hidden lamp', 'en_US'))->idsOf('product'));
    }

    public function testADeletedProductLeavesTheIndexWhenItsIdentifierIsSynchronized(): void
    {
        $product = $this->product('Old table');
        $id = $product->getId();
        $this->indexer->synchronize('product', [$id], self::LOCALES);

        $product->delete();
        $this->indexer->synchronize('product', [$id], self::LOCALES);

        self::assertFalse($this->engine->has('product', $id, 'en_US'));
    }

    public function testPlayingTheSameSynchronizationTwiceLeavesTheSameIndex(): void
    {
        $product = $this->product('Garden chair');

        $this->indexer->synchronize('product', [$product->getId()], self::LOCALES);
        $once = $this->engine->documents;
        $this->indexer->synchronize('product', [$product->getId(), $product->getId()], self::LOCALES);

        self::assertEquals($once, $this->engine->documents);
    }

    public function testTheTextOfACategoryIsIndexedWithoutItsMarkup(): void
    {
        $category = $this->fixtures->category();
        $category->setLocale('en_US')->setTitle('Garden')->setDescription('<p>Chairs</p><p>and&nbsp;tables</p>')->save();

        $this->indexer->synchronize('category', [$category->getId()], self::LOCALES);

        self::assertSame('Chairs and tables', $this->engine->get('category', $category->getId(), 'en_US')?->fields['description']);
    }

    public function testMarkupWrittenAsEntitiesIsNotIndexedAsMarkup(): void
    {
        $category = $this->fixtures->category();
        $category->setLocale('en_US')->setTitle('Garden')->setDescription('<p>Chairs &lt;script&gt;alert(1)&lt;/script&gt; for 5 &lt; 10 &euro;<img alt="big &gt; small"><!-- internal note --></p>')->save();

        $this->indexer->synchronize('category', [$category->getId()], self::LOCALES);

        self::assertSame('Chairs alert(1) for 5 < 10 €', $this->engine->get('category', $category->getId(), 'en_US')?->fields['description']);
    }

    public function testCodesAndComparisonsAreIndexedAsWritten(): void
    {
        $product = $this->product('Tension <12V', ['ref' => 'PSU<A>12']);
        $product->setLocale('en_US')->setDescription('<p>Cable <10 m long, 3 > 2</p>')->save();

        $this->indexer->synchronize('product', [$product->getId()], self::LOCALES);

        $document = $this->engine->get('product', $product->getId(), 'en_US');
        self::assertSame('Tension <12V', $document?->fields['title']);
        self::assertSame('PSU<A>12', $document->fields['ref']);
        self::assertSame('Cable <10 m long, 3 > 2', $document->fields['description']);
    }

    public function testACategoryTranslatedInOneLanguageHasNoDocumentInTheOthers(): void
    {
        $category = $this->fixtures->category();
        $category->setLocale('en_US')->setTitle('Garden')->save();

        $this->indexer->synchronize('category', [$category->getId()], self::LOCALES);

        self::assertSame('Garden', $this->engine->get('category', $category->getId(), 'en_US')?->fields['title']);
        self::assertFalse($this->engine->has('category', $category->getId(), 'fr_FR'));
    }

    public function testNothingIsReadWithoutAnEngine(): void
    {
        $provider = new SpyDocumentProvider('product');
        $indexer = new SearchIndexer(new SearchEngineRegistry([]), new SearchDocumentProviders([$provider]), new SearchLocales(), new NullLogger());

        $indexer->synchronize('product', [1, 2], self::LOCALES);

        self::assertSame([], $provider->calls);
    }

    public function testATranslationWithoutATitleHasNoDocument(): void
    {
        $product = $this->product('Garden chair');
        $product->setLocale('fr_FR')->setTitle(' ')->setChapo('Une chaise')->save();
        $category = $this->fixtures->category();
        $category->setLocale('en_US')->setTitle('Garden')->save();
        $category->setLocale('fr_FR')->setTitle('<p></p>')->setChapo('Jardin')->save();

        $this->indexer->synchronize('product', [$product->getId()], self::LOCALES);
        $this->indexer->synchronize('category', [$category->getId()], self::LOCALES);

        self::assertTrue($this->engine->has('product', $product->getId(), 'en_US'));
        self::assertFalse($this->engine->has('product', $product->getId(), 'fr_FR'));
        self::assertTrue($this->engine->has('category', $category->getId(), 'en_US'));
        self::assertFalse($this->engine->has('category', $category->getId(), 'fr_FR'));
    }

    public function testOnlyTheVisibleEntitiesAreVouchedFor(): void
    {
        $shown = $this->product('Garden chair');
        $hidden = $this->product('Hidden lamp', ['visible' => 0]);
        $shownCategory = $this->fixtures->category();
        $hiddenCategory = $this->fixtures->category();
        $hiddenCategory->setVisible(0)->save();

        self::assertSame([$shown->getId()], (new ProductDocumentProvider())->visibleIds([$shown->getId(), $hidden->getId(), 999_999_999]));
        self::assertSame([$shownCategory->getId()], (new CategoryDocumentProvider())->visibleIds([$shownCategory->getId(), $hiddenCategory->getId()]));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function product(string $title, array $overrides = []): Product
    {
        return $this->fixtures->product(
            $this->fixtures->category(),
            $this->fixtures->taxRule(),
            $this->fixtures->currency(),
            ['title' => $title, 'locale' => 'en_US'] + $overrides,
        );
    }
}
