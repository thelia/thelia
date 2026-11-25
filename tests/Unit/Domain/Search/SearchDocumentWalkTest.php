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
use Thelia\Domain\Search\Contract\SearchableDocumentProviderInterface;
use Thelia\Domain\Search\SearchDocumentWalk;
use Thelia\Domain\Search\UnorderedDocumentIdentifiersException;

final class SearchDocumentWalkTest extends TestCase
{
    public function testTheBatchesFollowEachOtherFromTheGivenIdentifierUpToTheLastDocument(): void
    {
        $batches = self::firstBatches(SearchDocumentWalk::batches($this->provider([2, 3, 5, 8, 13, 21, 34]), 3, 2));

        self::assertSame([[5, 8], [13, 21], [34]], $batches);
    }

    public function testAProviderWithoutDocumentsGivesNoBatch(): void
    {
        self::assertSame([], self::firstBatches(SearchDocumentWalk::batches($this->provider([]), 0, 100)));
    }

    /**
     * @param list<int> $answer
     */
    #[DataProvider('identifiersThatDoNotMoveOn')]
    public function testIdentifiersThatDoNotMoveOnStopTheWalk(array $answer): void
    {
        $provider = $this->createStub(SearchableDocumentProviderInterface::class);
        $provider->method('idsAfter')->willReturn($answer);

        $this->expectException(UnorderedDocumentIdentifiersException::class);

        self::firstBatches(SearchDocumentWalk::batches($provider, 10, 100));
    }

    /**
     * @return iterable<string, array{list<int>}>
     */
    public static function identifiersThatDoNotMoveOn(): iterable
    {
        yield 'the same batch again' => [[10, 11]];
        yield 'an identifier before the cursor' => [[4, 12]];
        yield 'not in ascending order' => [[14, 12]];
    }

    /**
     * @param \Generator<int, list<int>> $batches
     *
     * @return list<list<int>>
     */
    private static function firstBatches(\Generator $batches): array
    {
        $read = [];

        foreach ($batches as $batch) {
            $read[] = $batch;

            if (10 === \count($read)) {
                break;
            }
        }

        return $read;
    }

    /**
     * @param list<int> $ids
     */
    private function provider(array $ids): SearchableDocumentProviderInterface
    {
        $provider = $this->createStub(SearchableDocumentProviderInterface::class);
        $provider->method('idsAfter')->willReturnCallback(
            static fn (int $afterId, int $limit): array => \array_slice(array_values(array_filter($ids, static fn (int $id): bool => $id > $afterId)), 0, $limit),
        );

        return $provider;
    }
}
