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

namespace Thelia\Tests\Unit\Domain\Search\Dictionary;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Search\Dictionary\DictionarySearchTermExpander;
use Thelia\Domain\Search\Dictionary\SearchDictionary;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargets;
use Thelia\Domain\Search\Dictionary\SearchTermNormalizer;
use Thelia\Domain\Search\Document\SearchQuery;

final class SearchTermExpansionTest extends TestCase
{
    public function testAOneWaySynonymFindsItsEquivalentsOnly(): void
    {
        $index = SearchDictionary::synonymIndex([
            ['term' => 'tel', 'synonyms' => ['telephone', 'mobile'], 'bidirectional' => false],
        ]);

        self::assertSame(['telephone', 'mobile'], SearchDictionary::equivalentsOf('tel', $index));
        self::assertSame([], SearchDictionary::equivalentsOf('telephone', $index));
    }

    public function testATwoWaySynonymFindsTheTermFromEachEquivalent(): void
    {
        $index = SearchDictionary::synonymIndex([
            ['term' => 'tel', 'synonyms' => ['telephone', 'mobile'], 'bidirectional' => true],
        ]);

        self::assertSame(['telephone', 'mobile'], SearchDictionary::equivalentsOf('tel', $index));
        self::assertSame(['tel', 'mobile'], SearchDictionary::equivalentsOf('telephone', $index));
        self::assertSame(['tel', 'telephone'], SearchDictionary::equivalentsOf('mobile', $index));
    }

    public function testATwoWayGroupIsStoredOnceWhateverItsSize(): void
    {
        $equivalents = array_map(static fn (int $index): string => 'word'.$index, range(1, 50));
        $index = SearchDictionary::synonymIndex([
            ['term' => 'many', 'synonyms' => $equivalents, 'bidirectional' => true],
        ]);

        self::assertCount(1, $index['groups']);
        self::assertCount(51, $index['groups'][0]);
        self::assertCount(51, $index['references']);
        self::assertCount(50, SearchDictionary::equivalentsOf('word7', $index));
    }

    public function testADecomposedTermIsStoredComposed(): void
    {
        self::assertSame("caf\u{E9}", SearchTermNormalizer::normalize("Cafe\u{301}"));
    }

    public function testAChainOfSynonymsIsFollowedOneStepOnly(): void
    {
        $expander = $this->expander([
            ['term' => 'sofa', 'synonyms' => ['couch'], 'bidirectional' => false],
            ['term' => 'couch', 'synonyms' => ['settee'], 'bidirectional' => false],
        ]);

        self::assertSame(['couch'], $expander->expand('sofa', 'en_US'));
    }

    public function testATermWithoutSynonymsIsNotExpanded(): void
    {
        $expander = $this->expander([
            ['term' => 'tel', 'synonyms' => ['telephone'], 'bidirectional' => false],
        ]);

        self::assertSame([], $expander->expand('chair', 'en_US'));
        self::assertSame([], $expander->expand('   ', 'en_US'));
    }

    public function testSynonymsBelongToTheirLanguage(): void
    {
        $expander = $this->expander([
            ['term' => 'tel', 'synonyms' => ['téléphone'], 'bidirectional' => false],
        ], 'fr_FR');

        self::assertSame(['téléphone'], $expander->expand('tel', 'fr_FR'));
        self::assertSame([], $expander->expand('tel', 'en_US'));
    }

    public function testTheSearchedTermIsComparedNormalized(): void
    {
        $expander = $this->expander([
            ['term' => '  Smart   Phone ', 'synonyms' => ['MOBILE'], 'bidirectional' => false],
        ]);

        self::assertSame(['mobile'], $expander->expand("smart\tPHONE", 'en_US'));
    }

    public function testEachWordOfASentenceIsReplacedInTurn(): void
    {
        $expander = $this->expander([
            ['term' => 'red', 'synonyms' => ['crimson'], 'bidirectional' => false],
            ['term' => 'sofa', 'synonyms' => ['couch'], 'bidirectional' => false],
        ]);

        self::assertSame(['crimson sofa', 'red couch'], $expander->expand('red sofa', 'en_US'));
    }

    public function testAccentsAreIgnoredAsTheDatabaseIgnoresThem(): void
    {
        $expander = $this->expander([
            ['term' => 'écharpe', 'synonyms' => ['étole'], 'bidirectional' => true],
        ]);

        self::assertSame(['étole'], $expander->expand('echarpe', 'en_US'));
        self::assertSame(['écharpe'], $expander->expand('ETOLE', 'en_US'));
    }

    public function testTheWordsOfALongSentenceAreNotReplacedOneByOne(): void
    {
        $expander = $this->expander([
            ['term' => 'tel', 'synonyms' => ['telephone'], 'bidirectional' => false],
        ]);
        $words = array_fill(0, DictionarySearchTermExpander::MAX_EXPANDED_WORDS + 1, 'tel');

        self::assertSame([], $expander->expand(implode(' ', $words), 'en_US'));
        self::assertCount(1, $expander->expand('tel case', 'en_US'));
    }

    public function testTheExpansionIsCapped(): void
    {
        $synonyms = array_map(static fn (int $index): string => 'word'.$index, range(1, 30));
        $expander = $this->expander([
            ['term' => 'many', 'synonyms' => $synonyms, 'bidirectional' => false],
        ]);

        self::assertCount(SearchQuery::MAX_ALTERNATIVE_TERMS, $expander->expand('many', 'en_US'));
    }

    public function testAQueryRefusesMoreAlternativeTermsThanTheCap(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SearchQuery('many', 'en_US', alternativeTerms: array_fill(0, SearchQuery::MAX_ALTERNATIVE_TERMS + 1, 'word'));
    }

    public function testTheNormalizerLowersTrimsAndSingleSpaces(): void
    {
        self::assertSame('éclair au café', SearchTermNormalizer::normalize("  ÉCLAIR \n au   Café "));
        self::assertSame(['a', 'b'], SearchTermNormalizer::normalizeAll(['A', ' a ', '', 'b']));
    }

    #[DataProvider('paths')]
    public function testOnlyAPathOfTheShopIsInternal(string $path, bool $internal): void
    {
        self::assertSame($internal, SearchRedirectTargets::isInternalPath($path));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'path of the shop' => ['/fr/promotions.html', true];
        yield 'path with a query' => ['/index.php?view=category&category_id=3', true];
        yield 'protocol relative' => ['//evil.example/phish', false];
        yield 'absolute address' => ['https://evil.example/', false];
        yield 'backslash' => ['/\\evil.example', false];
        yield 'relative path' => ['promotions.html', false];
        yield 'control character' => ["/promo\r\nLocation: https://evil.example", false];
        yield 'tabulation' => ["/\t/evil.example", false];
        yield 'space' => ['/ /evil.example', false];
        yield 'empty' => ['', false];
        yield 'encoded slashes' => ['/%2F%2Fevil.example', true];
        yield 'encoded backslash' => ['/%5Cevil.example', true];
        yield 'dot segment' => ['/.//evil.example', true];
    }

    /**
     * @param list<array{term: string, synonyms: list<string>, bidirectional: bool}> $rows
     */
    private function expander(array $rows, string $locale = 'en_US'): DictionarySearchTermExpander
    {
        $index = SearchDictionary::synonymIndex($rows);

        $dictionary = new class($index, $locale) extends SearchDictionary {
            /**
             * @param array{groups: list<list<string>>, references: array<string, list<int>>} $index
             */
            public function __construct(private readonly array $index, private readonly string $locale)
            {
                parent::__construct(null);
            }

            public function synonymsOf(string $key, ?string $locale): array
            {
                return $locale === $this->locale ? self::equivalentsOf($key, $this->index) : [];
            }
        };

        return new DictionarySearchTermExpander($dictionary);
    }
}
