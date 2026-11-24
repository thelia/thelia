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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Domain\Search\Document\SearchQuery;
use Thelia\Domain\Search\EventListener\SearchIndexingListener;
use Thelia\Domain\Search\Message\IndexDocument;
use Thelia\Domain\Search\Message\IndexDocumentHandler;
use Thelia\Domain\Search\Message\RemoveDocument;
use Thelia\Domain\Search\Message\RemoveDocumentHandler;
use Thelia\Domain\Search\Provider\ProductDocumentProvider;
use Thelia\Domain\Search\SearchDocumentProviders;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Domain\Search\SearchIndexer;
use Thelia\Domain\Search\SearchIndexingQueue;
use Thelia\Domain\Search\SearchLocales;
use Thelia\Domain\Search\SearchService;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Search\InMemorySearchEngine;

final class SearchIndexingThroughTheQueueTest extends IntegrationTestCase
{
    private FixtureFactory $fixtures;
    private SearchEngineRegistry $engines;
    private SearchDocumentProviders $providers;
    private SearchIndexingListener $listener;

    /** @var list<IndexDocument|RemoveDocument> */
    private array $queued = [];

    /** @var array{Category, TaxRule, Currency}|null */
    private ?array $catalogue = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = $this->createFixtureFactory();
        $this->engines = new SearchEngineRegistry([new InMemorySearchEngine()]);
        $this->providers = new SearchDocumentProviders([new ProductDocumentProvider()]);
        $this->listener = new SearchIndexingListener($this->engines, new SearchIndexingQueue($this->worker(), new NullLogger()), new NullLogger());
        $this->dispatcher()->addSubscriber($this->listener);
    }

    protected function tearDown(): void
    {
        $this->dispatcher()->removeSubscriber($this->listener);

        parent::tearDown();
    }

    public function testARenamedProductIsFoundUnderItsNewTitleOnceTheWorkerPassed(): void
    {
        $product = $this->product('Garden chair');
        $this->listener->flush();

        self::assertSame([$product->getId()], $this->productsFoundBy('chair'));

        $product->setLocale('en_US')->setTitle('Patio bench')->save();

        self::assertSame([], $this->productsFoundBy('bench'));

        $this->listener->flush();

        self::assertSame([$product->getId()], $this->productsFoundBy('bench'));
        self::assertSame([], $this->productsFoundBy('chair'));
    }

    public function testABulkImportIsIndexedInBatchesOnlyOnceItIsOver(): void
    {
        $imported = [];

        foreach (range(1, 2 * SearchIndexingQueue::IDS_PER_MESSAGE + 1) as $index) {
            $imported[] = $this->product('Imported lantern '.$index)->getId();
        }

        self::assertSame([], $this->queued);
        self::assertSame(0, $this->productsFoundCountBy('lantern'));

        $this->listener->flush();

        $batches = array_values(array_filter($this->queued, static fn (object $message): bool => $message instanceof IndexDocument && ProductDocumentProvider::FAMILY === $message->family));
        self::assertSame([SearchIndexingQueue::IDS_PER_MESSAGE, SearchIndexingQueue::IDS_PER_MESSAGE, 1], array_map(static fn (IndexDocument $batch): int => \count($batch->ids), $batches));
        self::assertEqualsCanonicalizing($imported, array_merge(...array_map(static fn (IndexDocument $batch): array => $batch->ids, $batches)));
        self::assertSame(\count($imported), $this->productsFoundCountBy('lantern'));
    }

    private function worker(): MessageBusInterface
    {
        $queued = &$this->queued;
        $indexer = new SearchIndexer($this->engines, $this->providers, new SearchLocales(), new NullLogger());

        return new class($queued, new IndexDocumentHandler($indexer), new RemoveDocumentHandler($indexer)) implements MessageBusInterface {
            /** @param list<object> $queued */
            public function __construct(private array &$queued, private IndexDocumentHandler $index, private RemoveDocumentHandler $remove)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->queued[] = $message;

                match (true) {
                    $message instanceof IndexDocument => ($this->index)($message),
                    $message instanceof RemoveDocument => ($this->remove)($message),
                    default => null,
                };

                return new Envelope($message);
            }
        };
    }

    /**
     * @return list<int>
     */
    private function productsFoundBy(string $term): array
    {
        return $this->search()->search(new SearchQuery($term, 'en_US', [ProductDocumentProvider::FAMILY]))?->idsOf(ProductDocumentProvider::FAMILY) ?? [];
    }

    private function productsFoundCountBy(string $term): int
    {
        return $this->search()->search(new SearchQuery($term, 'en_US', [ProductDocumentProvider::FAMILY]))->total ?? 0;
    }

    private function search(): SearchService
    {
        return new SearchService($this->engines, $this->providers, [], [], new NullLogger());
    }

    private function product(string $title): Product
    {
        $this->catalogue ??= [$this->fixtures->category(), $this->fixtures->taxRule(), $this->fixtures->currency()];

        return $this->fixtures->product(...[...$this->catalogue, ['title' => $title]]);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
