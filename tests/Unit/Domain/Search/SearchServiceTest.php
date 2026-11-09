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

namespace Thelia\Tests\Unit\Domain\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Thelia\Domain\Search\Contract\SearchEngineInterface;
use Thelia\Domain\Search\Contract\SearchRedirectResolverInterface;
use Thelia\Domain\Search\Contract\SearchScope;
use Thelia\Domain\Search\Contract\SearchTermRewriterInterface;
use Thelia\Domain\Search\Document\SearchDocument;
use Thelia\Domain\Search\Document\SearchQuery;
use Thelia\Domain\Search\Document\SearchResult;
use Thelia\Domain\Search\SearchDocumentProviders;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Domain\Search\SearchService;
use Thelia\Tests\Support\Search\InMemorySearchEngine;
use Thelia\Tests\Support\Search\SpyDocumentProvider;

final class SearchServiceTest extends TestCase
{
    /** @var list<array{level: string, message: string}> */
    private array $logs = [];

    public function testWithoutAnEngineTheCallerSearchesTheDatabase(): void
    {
        $service = $this->service(null);

        self::assertFalse($service->hasEngine());
        self::assertNull($service->search(new SearchQuery('phone', 'en_US')));
    }

    public function testTheEngineAnswersWithTheRewrittenTerm(): void
    {
        $engine = $this->engineWith(new SearchDocument('product', 7, 'en_US', SearchScope::Shop, true, ['title' => 'Smart telephone']));
        $service = $this->service($engine, rewriters: [$this->rewriter('tel', 'telephone')]);

        $result = $service->search(new SearchQuery('tel', 'en_US'));

        self::assertNotNull($result);
        self::assertSame('telephone', $engine->lastQuery?->term);
        self::assertSame([7], $result->idsOf('product'));
    }

    public function testRewritersApplyOneAfterTheOther(): void
    {
        $service = $this->service(null, rewriters: [$this->rewriter('tel', 'phone'), $this->rewriter('phone', 'telephone')]);

        self::assertSame('telephone', $service->rewrite('tel', 'en_US'));
    }

    public function testAnUnavailableEngineHandsTheSearchBackAndSaysSo(): void
    {
        $engine = $this->engineWith(new SearchDocument('product', 7, 'en_US', SearchScope::Shop, true, ['title' => 'Phone']));
        $engine->available = false;

        self::assertNull($this->service($engine)->search(new SearchQuery('phone', 'en_US')));
        self::assertSame('warning', $this->logs[0]['level'] ?? null);
    }

    public function testAFailingEngineHandsTheSearchBackAndSaysSo(): void
    {
        $failing = new class implements SearchEngineInterface {
            public function isAvailable(): bool
            {
                return true;
            }

            public function index(array $documents): void
            {
            }

            public function remove(string $family, array $ids, ?string $locale): void
            {
            }

            public function purge(string $family): void
            {
            }

            public function search(SearchQuery $query): SearchResult
            {
                throw new \RuntimeException('Connection refused');
            }
        };

        self::assertNull($this->service($failing)->search(new SearchQuery('phone', 'en_US')));
        self::assertSame('error', $this->logs[0]['level'] ?? null);
    }

    public function testAnInvisibleDocumentNeverReachesAShopSearch(): void
    {
        $engine = $this->engineWith(
            new SearchDocument('product', 1, 'en_US', SearchScope::Shop, true, ['title' => 'Phone']),
            new SearchDocument('product', 2, 'en_US', SearchScope::Shop, false, ['title' => 'Phone prototype']),
        );

        $result = $this->service($engine)->search(new SearchQuery('phone', 'en_US'));

        self::assertSame([1], $result?->idsOf('product'));
    }

    public function testTheFirstResolverWithARedirectWins(): void
    {
        $service = $this->service(null, redirectResolvers: [
            $this->resolver([]),
            $this->resolver(['sales' => '/sales']),
            $this->resolver(['sales' => '/other']),
        ]);

        self::assertSame('/sales', $service->redirectFor('sales', 'en_US'));
        self::assertNull($service->redirectFor('phone', 'en_US'));
    }

    public function testARewriterThatThrowsLeavesTheTermToTheNextOne(): void
    {
        $failing = new class implements SearchTermRewriterInterface {
            public function rewrite(string $term, ?string $locale): string
            {
                throw new \RuntimeException('Synonyms table missing');
            }
        };
        $service = $this->service(null, rewriters: [$failing, $this->rewriter('tel', 'telephone')]);

        self::assertSame('telephone', $service->rewrite('tel', 'en_US'));
        self::assertSame('error', $this->logs[0]['level'] ?? null);
    }

    public function testAResolverThatThrowsLeavesTheRedirectToTheNextOne(): void
    {
        $failing = new class implements SearchRedirectResolverInterface {
            public function redirectFor(string $term, ?string $locale): ?string
            {
                throw new \RuntimeException('Redirections table missing');
            }
        };
        $service = $this->service(null, redirectResolvers: [$failing, $this->resolver(['sales' => '/sales'])]);

        self::assertSame('/sales', $service->redirectFor('sales', 'en_US'));
        self::assertSame('error', $this->logs[0]['level'] ?? null);
    }

    #[DataProvider('pathsOutsideTheShop')]
    public function testARedirectOutsideTheShopIsNeverFollowed(string $path): void
    {
        $service = $this->service(null, redirectResolvers: [$this->resolver(['sales' => $path]), $this->resolver(['sales' => '/sales'])]);

        self::assertSame('/sales', $service->redirectFor('sales', 'en_US'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsOutsideTheShop(): iterable
    {
        yield 'absolute URL' => ['https://evil.example/sales'];
        yield 'protocol-relative URL' => ['//evil.example/sales'];
        yield 'backslash read as a slash' => ['/\\evil.example/sales'];
        yield 'tab dropped by the browser' => ["/\t/evil.example/sales"];
        yield 'script' => ['javascript:alert(1)'];
        yield 'relative path' => ['sales'];
        yield 'empty' => [''];
    }

    public function testAnEmptyTermFindsNothingWithoutAskingTheEngine(): void
    {
        $engine = $this->engineWith(new SearchDocument('product', 7, 'en_US', SearchScope::Shop, true, ['title' => 'Phone']));

        $result = $this->service($engine)->search(new SearchQuery('  ', 'en_US'));

        self::assertNotNull($result);
        self::assertSame([], $result->hits);
        self::assertSame(0, $result->total);
        self::assertNull($engine->lastQuery);
    }

    public function testATermRewrittenToNothingFindsNothingWithoutAskingTheEngine(): void
    {
        $engine = $this->engineWith(new SearchDocument('product', 7, 'en_US', SearchScope::Shop, true, ['title' => 'Phone']));

        $result = $this->service($engine, rewriters: [$this->rewriter('the', '')])->search(new SearchQuery('the', 'en_US'));

        self::assertSame(0, $result?->total);
        self::assertNull($engine->lastQuery);
    }

    public function testAHitHiddenSinceItWasIndexedIsLeftOut(): void
    {
        $engine = $this->engineWith(
            new SearchDocument('product', 1, 'en_US', SearchScope::Shop, true, ['title' => 'Phone']),
            new SearchDocument('product', 2, 'en_US', SearchScope::Shop, true, ['title' => 'Phone case']),
            new SearchDocument('brand', 3, 'en_US', SearchScope::Shop, true, ['title' => 'Phone & co']),
        );
        $products = new SpyDocumentProvider('product', [1]);
        $brands = new SpyDocumentProvider('brand');

        $result = $this->service($engine, providers: [$products, $brands])->search(new SearchQuery('phone', 'en_US'));

        self::assertSame([1], $result?->idsOf('product'));
        self::assertSame([3], $result->idsOf('brand'));
        self::assertSame(2, $result->total);
        self::assertSame(['visibleIds'], $products->calls);
        self::assertSame(['visibleIds'], $brands->calls);
    }

    public function testAHitOfAFamilyNoProviderVouchesForIsLeftOut(): void
    {
        $engine = $this->engineWith(new SearchDocument('blog_post', 1, 'en_US', SearchScope::Shop, true, ['title' => 'Phone review']));

        self::assertSame([], $this->service($engine)->search(new SearchQuery('phone', 'en_US'))?->hits);
    }

    public function testASearchOfHiddenDocumentsTooKeepsEveryHit(): void
    {
        $engine = $this->engineWith(new SearchDocument('product', 2, 'en_US', SearchScope::Shop, false, ['title' => 'Phone prototype']));
        $products = new SpyDocumentProvider('product', []);

        $result = $this->service($engine, providers: [$products])->search(new SearchQuery('phone', 'en_US', visibleOnly: false));

        self::assertSame([2], $result?->idsOf('product'));
        self::assertSame([], $products->calls);
    }

    public function testAnEngineThatCannotBeBuiltHandsTheSearchBack(): void
    {
        $service = new SearchService(
            new SearchEngineRegistry((static function (): iterable {
                throw new \RuntimeException('Missing API key');
                yield new InMemorySearchEngine();
            })()),
            new SearchDocumentProviders([]),
            [],
            [],
            $this->logger(),
        );

        self::assertNull($service->search(new SearchQuery('phone', 'en_US')));
        self::assertSame('error', $this->logs[0]['level'] ?? null);
    }

    private function engineWith(SearchDocument ...$documents): InMemorySearchEngine
    {
        $engine = new InMemorySearchEngine();
        $engine->index(array_values($documents));

        return $engine;
    }

    /**
     * @param list<SearchTermRewriterInterface>     $rewriters
     * @param list<SearchRedirectResolverInterface> $redirectResolvers
     * @param list<SpyDocumentProvider>|null        $providers
     */
    private function service(?SearchEngineInterface $engine, array $rewriters = [], array $redirectResolvers = [], ?array $providers = null): SearchService
    {
        return new SearchService(
            new SearchEngineRegistry(null === $engine ? [] : [$engine]),
            new SearchDocumentProviders($providers ?? [new SpyDocumentProvider('product')]),
            $rewriters,
            $redirectResolvers,
            $this->logger(),
        );
    }

    private function logger(): AbstractLogger
    {
        $logs = &$this->logs;

        return new class($logs) extends AbstractLogger {
            /** @param list<array{level: string, message: string}> $logs */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };
    }

    private function rewriter(string $from, string $to): SearchTermRewriterInterface
    {
        return new class($from, $to) implements SearchTermRewriterInterface {
            public function __construct(private string $from, private string $to)
            {
            }

            public function rewrite(string $term, ?string $locale): string
            {
                return $term === $this->from ? $this->to : $term;
            }
        };
    }

    /**
     * @param array<string, string> $redirects
     */
    private function resolver(array $redirects): SearchRedirectResolverInterface
    {
        return new class($redirects) implements SearchRedirectResolverInterface {
            /** @param array<string, string> $redirects */
            public function __construct(private array $redirects)
            {
            }

            public function redirectFor(string $term, ?string $locale): ?string
            {
                return $this->redirects[$term] ?? null;
            }
        };
    }
}
