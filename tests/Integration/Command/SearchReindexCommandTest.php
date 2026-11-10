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

namespace Thelia\Tests\Integration\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Thelia\Command\SearchReindexCommand;
use Thelia\Domain\Search\Contract\SearchableDocumentProviderInterface;
use Thelia\Domain\Search\Message\IndexDocument;
use Thelia\Domain\Search\Provider\BrandDocumentProvider;
use Thelia\Domain\Search\Provider\ProductDocumentProvider;
use Thelia\Domain\Search\SearchDocumentProviders;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Domain\Search\SearchIndexer;
use Thelia\Domain\Search\SearchIndexingQueue;
use Thelia\Domain\Search\SearchLocales;
use Thelia\Model\Brand;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Search\InMemorySearchEngine;
use Thelia\Tests\Support\Search\SpyDocumentProvider;

final class SearchReindexCommandTest extends IntegrationTestCase
{
    private const int INDEX_CALLS_PER_BRAND = 2;

    private InMemorySearchEngine $engine;
    private ArrayAdapter $checkpoints;
    private LockFactory $locks;

    /** @var list<Envelope> */
    private array $queued = [];

    private string $lastDisplay = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new InMemorySearchEngine();
        $this->checkpoints = new ArrayAdapter();
        $this->locks = new LockFactory(new InMemoryStore());
        $this->queued = [];
    }

    public function testAFullRunIndexesEveryLocaleWithoutEmptyingTheIndex(): void
    {
        $brand = $this->brand('Garden & co', 'Jardin et cie');
        $tester = $this->reindex(['--family' => ['brand'], '--batch-size' => '2']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([], $this->engine->purgedFamilies);
        self::assertSame('Garden & co', $this->engine->get('brand', $brand->getId(), 'en_US')?->fields['title']);
        self::assertSame('Jardin et cie', $this->engine->get('brand', $brand->getId(), 'fr_FR')?->fields['title']);
    }

    public function testPurgeEmptiesTheFamilyBeforeIndexingIt(): void
    {
        $brand = $this->brand('Garden & co', 'Jardin et cie');

        $tester = $this->reindex(['--family' => ['brand'], '--purge' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(['brand'], $this->engine->purgedFamilies);
        self::assertTrue($this->engine->has('brand', $brand->getId(), 'en_US'));
    }

    public function testPurgeCannotEmptyTheLocalesARunLeavesAlone(): void
    {
        $tester = $this->reindex(['--family' => ['brand'], '--purge' => true, '--locale' => ['en_US']]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([], $this->engine->purgedFamilies);
    }

    public function testAnInterruptedRunResumesAfterTheLastIndexedBatchWithoutPurgingAgain(): void
    {
        $first = $this->brand('First', 'Premier');
        $second = $this->brand('Second', 'Deuxième');
        $this->engine->indexCallsBeforeFailure = self::INDEX_CALLS_PER_BRAND;

        $interrupted = $this->reindex(['--family' => ['brand'], '--batch-size' => '1', '--purge' => true]);

        self::assertSame(Command::FAILURE, $interrupted->getStatusCode());
        self::assertFalse($this->engine->has('brand', $second->getId(), 'en_US'));

        $this->engine->indexCallsBeforeFailure = null;
        $resumed = $this->reindex(['--family' => ['brand'], '--batch-size' => '1', '--purge' => true]);

        self::assertSame(Command::SUCCESS, $resumed->getStatusCode());
        self::assertStringContainsString('resuming after #', $resumed->getDisplay());
        self::assertSame(['brand'], $this->engine->purgedFamilies);
        self::assertTrue($this->engine->has('brand', $first->getId(), 'en_US'));
        self::assertTrue($this->engine->has('brand', $second->getId(), 'fr_FR'));

        $again = $this->reindex(['--family' => ['brand'], '--batch-size' => '1']);

        self::assertStringNotContainsString('resuming', $again->getDisplay());
    }

    public function testAPurgeAskedAfterAnInterruptedRunWithoutOneStartsOver(): void
    {
        $this->brand('First', 'Premier');
        $this->brand('Second', 'Deuxième');
        $this->engine->indexCallsBeforeFailure = self::INDEX_CALLS_PER_BRAND;
        $this->reindex(['--family' => ['brand'], '--batch-size' => '1']);
        $this->engine->indexCallsBeforeFailure = null;

        $purged = $this->reindex(['--family' => ['brand'], '--batch-size' => '1', '--purge' => true]);

        self::assertSame(Command::SUCCESS, $purged->getStatusCode());
        self::assertStringNotContainsString('resuming', $purged->getDisplay());
        self::assertSame(['brand'], $this->engine->purgedFamilies);
    }

    public function testAResumedRunSkipsTheFamiliesItFinished(): void
    {
        $alpha = new SpyDocumentProvider('alpha', ids: [1, 2]);
        $beta = new SpyDocumentProvider('beta', ids: [1, 2]);
        $beta->failing = true;

        self::assertSame(Command::FAILURE, $this->reindex(['--batch-size' => '1'], providers: [$alpha, $beta])->getStatusCode());

        $alpha->calls = [];
        $beta->failing = false;
        $resumed = $this->reindex(['--batch-size' => '1'], providers: [$alpha, $beta]);

        self::assertSame(Command::SUCCESS, $resumed->getStatusCode());
        self::assertStringContainsString('alpha, already indexed', $resumed->getDisplay());
        self::assertSame([], $alpha->calls);
        self::assertContains('documents', $beta->calls);

        $alpha->calls = [];
        $this->reindex(['--batch-size' => '1'], providers: [$alpha, $beta]);

        self::assertContains('documents', $alpha->calls);
    }

    public function testThePointARunResumesFromExpiresAfterADay(): void
    {
        $failing = new SpyDocumentProvider('beta', ids: [1, 2]);
        $failing->failing = true;
        $before = microtime(true);

        $this->reindex(['--batch-size' => '1'], providers: [new SpyDocumentProvider('alpha', ids: [1, 2]), $failing]);

        $expiries = \Closure::bind(static fn (ArrayAdapter $pool): array => $pool->expiries, null, ArrayAdapter::class)($this->checkpoints);
        self::assertCount(1, $expiries);
        $expiry = reset($expiries);
        self::assertEqualsWithDelta($before + 86400, $expiry, 5);
    }

    public function testTheLocalesOfARunAreTheSameInAnyOrder(): void
    {
        $this->brand('First', 'Premier');
        $this->brand('Second', 'Deuxième');
        $this->engine->indexCallsBeforeFailure = self::INDEX_CALLS_PER_BRAND;
        $this->reindex(['--family' => ['brand'], '--batch-size' => '1', '--locale' => ['fr_FR', 'en_US']]);
        $this->engine->indexCallsBeforeFailure = null;

        $resumed = $this->reindex(['--family' => ['brand'], '--batch-size' => '1', '--locale' => ['en_US', 'fr_FR']]);

        self::assertStringContainsString('resuming after #', $resumed->getDisplay());
    }

    public function testALocaleThatIsNotIndexedIsRefused(): void
    {
        $tester = $this->reindex(['--family' => ['brand'], '--locale' => ['xx_XX']]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('xx_XX', $tester->getDisplay());
        self::assertSame([], $this->engine->documents);
    }

    #[DataProvider('batchSizesThatAreNotOnes')]
    public function testABatchSizeThatIsNotAWholeNumberIsRefused(string $batchSize): void
    {
        $brand = $this->brand('Garden & co', 'Jardin et cie');

        $tester = $this->reindex(['--family' => ['brand'], '--batch-size' => $batchSize]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertFalse($this->engine->has('brand', $brand->getId(), 'en_US'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function batchSizesThatAreNotOnes(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'word' => ['all'];
        yield 'trailing letters' => ['10k'];
    }

    public function testTheQueueOptionSendsTheBatchesToTheHeavyQueueByMessagesOfAHundred(): void
    {
        $provider = new SpyDocumentProvider('alpha', ids: range(1, SearchIndexingQueue::IDS_PER_MESSAGE + 1));

        $this->reindex(['--batch-size' => '1000', '--queue' => true], providers: [$provider]);

        self::assertCount(2, $this->queued);
        self::assertSame(range(1, SearchIndexingQueue::IDS_PER_MESSAGE), $this->messageOf($this->queued[0])->ids);
        self::assertSame([SearchIndexingQueue::IDS_PER_MESSAGE + 1], $this->messageOf($this->queued[1])->ids);

        foreach ($this->queued as $envelope) {
            self::assertSame([SearchReindexCommand::QUEUE_TRANSPORT], $envelope->last(TransportNamesStamp::class)?->getTransportNames());
        }

        self::assertNotContains('documents', $provider->calls);
        self::assertStringContainsString('messenger:failed:retry', $this->lastDisplay);
    }

    public function testTheLockIsRefreshedBeforeEachBatch(): void
    {
        $provider = new SpyDocumentProvider('alpha', ids: [1, 2]);
        $this->locks = new LockFactory(new class($provider) implements PersistingStoreInterface {
            private InMemoryStore $store;

            public function __construct(private SpyDocumentProvider $provider)
            {
                $this->store = new InMemoryStore();
            }

            public function save(Key $key): void
            {
                $this->store->save($key);
            }

            public function delete(Key $key): void
            {
                $this->store->delete($key);
            }

            public function exists(Key $key): bool
            {
                return $this->store->exists($key);
            }

            public function putOffExpiration(Key $key, float $ttl): void
            {
                $this->provider->calls[] = 'refresh';
                $this->store->putOffExpiration($key, $ttl);
            }
        });

        $this->reindex(['--batch-size' => '1'], providers: [$provider]);

        $calls = array_values(array_filter($provider->calls, static fn (string $call): bool => \in_array($call, ['refresh', 'idsAfter'], true)));
        $batches = array_keys($calls, 'idsAfter', true);
        self::assertCount(3, $batches);

        foreach ($batches as $position) {
            self::assertSame('refresh', $calls[$position - 1]);
        }
    }

    public function testALostLockStopsTheRun(): void
    {
        $alpha = new SpyDocumentProvider('alpha', ids: [1, 2]);
        $beta = new SpyDocumentProvider('beta', ids: [1, 2]);
        $this->locks = new LockFactory(new class implements PersistingStoreInterface {
            private InMemoryStore $store;
            private int $putOffs = 0;

            public function __construct()
            {
                $this->store = new InMemoryStore();
            }

            public function save(Key $key): void
            {
                $this->store->save($key);
            }

            public function delete(Key $key): void
            {
                $this->store->delete($key);
            }

            public function exists(Key $key): bool
            {
                return $this->store->exists($key);
            }

            public function putOffExpiration(Key $key, float $ttl): void
            {
                if (++$this->putOffs > 1) {
                    throw new LockConflictedException();
                }

                $this->store->putOffExpiration($key, $ttl);
            }
        });

        $tester = $this->reindex(['--batch-size' => '1'], providers: [$alpha, $beta]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(1, substr_count($tester->getDisplay(), 'lock of the reindexing was lost'));
        self::assertStringNotContainsString('beta', $tester->getDisplay());
    }

    public function testAProviderThatDoesNotReturnAscendingIdentifiersStopsItsFamily(): void
    {
        $provider = new SpyDocumentProvider('alpha', ids: [3, 1, 2]);

        $tester = $this->reindex(['--batch-size' => '2'], providers: [$provider]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('ascending order', $tester->getDisplay());
        self::assertNotContains('documents', $provider->calls);
    }

    public function testARunWithoutAQueueSaysTheWritesAreIndexedInline(): void
    {
        self::assertStringContainsString('No queue holds the jobs', $this->reindex(['--family' => ['brand']], jobQueueDsn: 'sync://')->getDisplay());
        self::assertStringNotContainsString('No queue holds the jobs', $this->reindex(['--family' => ['brand']], jobQueueDsn: 'doctrine://default')->getDisplay());
    }

    public function testASecondRunWaitsForNothingWhileTheFirstHoldsTheLock(): void
    {
        $lock = $this->locks->createLock('thelia.search.reindex');
        $lock->acquire();

        $tester = $this->reindex(['--family' => ['brand'], '--purge' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([], $this->engine->purgedFamilies);
        $lock->release();
    }

    public function testAnUnknownFamilyIsRefused(): void
    {
        self::assertSame(Command::FAILURE, $this->reindex(['--family' => ['order']])->getStatusCode());
    }

    public function testWithoutAnEngineThereIsNothingToIndex(): void
    {
        $tester = $this->reindex([], withEngine: false);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No search engine', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed>                           $options
     * @param list<SearchableDocumentProviderInterface>|null $providers
     */
    private function reindex(array $options, bool $withEngine = true, ?array $providers = null, string $jobQueueDsn = 'doctrine://default'): CommandTester
    {
        $registry = new SearchEngineRegistry($withEngine ? [$this->engine] : []);
        $providers = new SearchDocumentProviders($providers ?? [new ProductDocumentProvider(), new BrandDocumentProvider()]);
        $queued = &$this->queued;

        $tester = new CommandTester(new SearchReindexCommand(
            $registry,
            $providers,
            new SearchIndexer($registry, $providers, new SearchLocales(), new NullLogger()),
            new SearchLocales(),
            new class($queued) implements MessageBusInterface {
                /** @param list<Envelope> $queued */
                public function __construct(private array &$queued)
                {
                }

                public function dispatch(object $message, array $stamps = []): Envelope
                {
                    $envelope = Envelope::wrap($message, $stamps);
                    $this->queued[] = $envelope;

                    return $envelope;
                }
            },
            $this->locks,
            $this->checkpoints,
            $jobQueueDsn,
        ));
        $tester->execute($options);
        $this->lastDisplay = $tester->getDisplay();

        return $tester;
    }

    private function messageOf(Envelope $envelope): IndexDocument
    {
        $message = $envelope->getMessage();
        self::assertInstanceOf(IndexDocument::class, $message);

        return $message;
    }

    private function brand(string $english, string $french): Brand
    {
        $brand = $this->createFixtureFactory()->brand(['title' => $english]);
        $brand->setLocale('fr_FR')->setTitle($french)->save();

        return $brand;
    }
}
