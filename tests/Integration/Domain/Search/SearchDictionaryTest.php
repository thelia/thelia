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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Search\Dictionary\SearchDictionary;
use Thelia\Domain\Search\Dictionary\SearchDictionaryImporter;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargetType;
use Thelia\Domain\Search\Dictionary\SearchTermRedirectValidator;
use Thelia\Domain\Search\Event\SearchSynonymWriteEvent;
use Thelia\Domain\Search\Event\SearchTermRedirectWriteEvent;
use Thelia\Domain\Search\EventListener\SearchTermRedirectTargetListener;
use Thelia\Domain\Search\SearchService;
use Thelia\Model\Event\CategoryEvent;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\SearchSynonymQuery;
use Thelia\Model\SearchTermRedirect;
use Thelia\Model\SearchTermRedirectQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class SearchDictionaryTest extends IntegrationTestCase
{
    private FixtureFactory $fixtures;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function testASearchedTermIsRedirectedToItsCategory(): void
    {
        $category = $this->fixtures->category();
        $this->redirect('Garden  Sale', SearchRedirectTargetType::CATEGORY, $category->getId());

        $path = $this->search()->redirectFor('garden sale', 'en_US');

        self::assertNotNull($path);
        self::assertStringStartsWith('/', $path);
        self::assertSame(parse_url((string) $category->getUrl('en_US'), \PHP_URL_PATH), parse_url($path, \PHP_URL_PATH));
        self::assertNull($this->search()->redirectFor('garden sale', 'fr_FR'));
    }

    #[DataProvider('targetTypes')]
    public function testARedirectionToADeletedTargetIsDeactivatedOnceTheRequestEnds(string $type): void
    {
        $target = match ($type) {
            SearchRedirectTargetType::PRODUCT => $this->fixtures->product($this->fixtures->category(), $this->fixtures->taxRule(), $this->fixtures->currency()),
            SearchRedirectTargetType::CATEGORY => $this->fixtures->category(),
            SearchRedirectTargetType::CONTENT => $this->fixtures->content($this->fixtures->folder()),
            SearchRedirectTargetType::FOLDER => $this->fixtures->folder(),
            default => $this->fixtures->brand(),
        };
        $redirect = $this->redirect('garden', $type, $target->getId());

        self::assertNotNull($this->search()->redirectFor('garden', 'en_US'));

        $target->delete();

        self::assertSame(1, SearchTermRedirectQuery::create()->findPk($redirect->getId())?->getActive(), 'Nothing is written inside the transaction of the deletion.');

        static::getContainer()->get(SearchTermRedirectTargetListener::class)->deactivateTheRedirectionsOfTheDeletedTargets();

        self::assertSame(0, SearchTermRedirectQuery::create()->findPk($redirect->getId())?->getActive());
        self::assertNull($this->search()->redirectFor('garden', 'en_US'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function targetTypes(): iterable
    {
        foreach (SearchRedirectTargetType::ENTITIES as $type) {
            yield $type => [$type];
        }
    }

    public function testARedirectionWhoseTargetDeletionWasRolledBackStaysActive(): void
    {
        $category = $this->fixtures->category();
        $redirect = $this->redirect('garden', SearchRedirectTargetType::CATEGORY, $category->getId());
        $listener = static::getContainer()->get(SearchTermRedirectTargetListener::class);

        $listener->onCategoryDelete(new CategoryEvent($category));
        $listener->deactivateTheRedirectionsOfTheDeletedTargets();

        self::assertSame(1, SearchTermRedirectQuery::create()->findPk($redirect->getId())?->getActive());
    }

    public function testARedirectionToAHiddenTargetIsNotFollowed(): void
    {
        $category = $this->fixtures->category(['visible' => 0]);
        $this->redirect('garden', SearchRedirectTargetType::CATEGORY, $category->getId());

        self::assertNull($this->search()->redirectFor('garden', 'en_US'));
    }

    public function testARedirectionToAMissingTargetIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->redirect('garden', SearchRedirectTargetType::CATEGORY, 999999999);
    }

    public function testARedirectionToAnotherSiteIsRefused(): void
    {
        foreach (['https://evil.example/', '//evil.example/', '/\\evil.example', 'promo.html'] as $url) {
            try {
                $this->redirect('promo', SearchRedirectTargetType::URL, url: $url);
                self::fail(\sprintf('"%s" was accepted.', $url));
            } catch (\InvalidArgumentException) {
            }
        }

        $this->redirect('promo', SearchRedirectTargetType::URL, url: '/promotions');

        self::assertSame('/promotions', $this->search()->redirectFor('PROMO', 'en_US'));
    }

    public function testATermIsRedirectedOnlyOncePerLanguage(): void
    {
        $this->redirect('promo', SearchRedirectTargetType::URL, url: '/promotions');

        $this->expectException(\InvalidArgumentException::class);

        $this->redirect(' Promo ', SearchRedirectTargetType::URL, url: '/sales');
    }

    public function testARedirectionOfAWordManyProductsBearIsFlaggedAsCommon(): void
    {
        $category = $this->fixtures->category();
        $taxRule = $this->fixtures->taxRule();
        $currency = $this->fixtures->currency();

        foreach (range(1, SearchTermRedirectValidator::COMMON_TERM_THRESHOLD + 1) as $index) {
            $this->fixtures->product($category, $taxRule, $currency, ['title' => 'Garden chair '.$index]);
        }

        $common = (new SearchTermRedirectWriteEvent())
            ->setLangId($this->english()->getId())
            ->setTerm('Chair')
            ->setTargetType(SearchRedirectTargetType::URL)
            ->setUrl('/chairs');
        $this->dispatcher()->dispatch($common, TheliaEvents::SEARCH_TERM_REDIRECT_CREATE);

        $rare = (new SearchTermRedirectWriteEvent())
            ->setLangId($this->english()->getId())
            ->setTerm('hammock')
            ->setTargetType(SearchRedirectTargetType::URL)
            ->setUrl('/hammocks');
        $this->dispatcher()->dispatch($rare, TheliaEvents::SEARCH_TERM_REDIRECT_CREATE);

        self::assertTrue($common->isCommonTerm());
        self::assertFalse($rare->isCommonTerm());
    }

    public function testSynonymsApplyWithoutAnEngineAndFollowEveryWrite(): void
    {
        self::assertFalse($this->search()->hasEngine());

        $event = $this->synonym('tel', ['Telephone', 'mobile', 'tel']);

        self::assertSame(['telephone', 'mobile'], $this->search()->expand('Tel', 'en_US'));
        self::assertSame("telephone\nmobile", SearchSynonymQuery::create()->findPk($event->getSynonym()?->getId())?->getSynonyms());

        $this->dispatcher()->dispatch($event->setSynonyms(['smartphone'])->setBidirectional(true), TheliaEvents::SEARCH_SYNONYM_UPDATE);

        self::assertSame(['smartphone'], $this->search()->expand('tel', 'en_US'));
        self::assertSame(['tel'], $this->search()->expand('smartphone', 'en_US'));

        $this->dispatcher()->dispatch($event, TheliaEvents::SEARCH_SYNONYM_TOGGLE);

        self::assertSame([], $this->search()->expand('tel', 'en_US'));
    }

    public function testASynonymNeedsAnEquivalentOtherThanItself(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->synonym('tel', [' TEL ', '']);
    }

    public function testTheImportCreatesUpdatesAndReportsTheRefusedLines(): void
    {
        $this->synonym('sofa', ['couch']);

        $report = $this->importer()->importSynonyms($this->csv(
            "locale;term;synonyms;bidirectional\n"
            ."en_US;tel;telephone|mobile;1\n"
            ."en_US;sofa;settee;0\n"
            ."xx_XX;chair;seat;0\n"
            ."en_US;lamp;;0\n"
        ));

        self::assertSame(1, $report->created);
        self::assertSame(1, $report->updated);
        self::assertSame([4, 5], array_keys($report->refused));
        self::assertSame(['settee'], $this->search()->expand('sofa', 'en_US'));
        self::assertSame(['tel', 'mobile'], $this->search()->expand('telephone', 'en_US'));
    }

    public function testTheImportOfRedirectionsRefusesAnotherSite(): void
    {
        $category = $this->fixtures->category();

        $report = $this->importer()->importRedirects($this->csv(
            "en_US;garden;category;{$category->getId()}\n"
            ."en_US;promo;url;https://evil.example/\n"
            ."en_US;sale;url;/sales\n"
        ));

        self::assertSame(2, $report->created);
        self::assertSame([2], array_keys($report->refused));
        self::assertSame('/sales', $this->search()->redirectFor('sale', 'en_US'));
    }

    public function testTheDictionaryIsKeptInTheSharedCacheUntilAWrite(): void
    {
        $this->synonym('tel', ['telephone']);
        $dictionary = static::getContainer()->get(SearchDictionary::class);
        $dictionary->forgetAfterTheRequest();
        $dictionary->reset();

        self::assertSame(['telephone'], $this->search()->expand('tel', 'en_US'));

        SearchSynonymQuery::create()->filterByTerm('tel')->update(['Synonyms' => 'mobile']);
        $dictionary->reset();

        self::assertSame(['telephone'], $this->search()->expand('tel', 'en_US'));

        $this->synonym('sofa', ['couch']);

        self::assertSame(['mobile'], $this->search()->expand('tel', 'en_US'));
    }

    public function testTheImportOfARedirectionKeepsItInactive(): void
    {
        $redirect = $this->redirect('sale', SearchRedirectTargetType::URL, url: '/sales');
        $this->dispatcher()->dispatch((new SearchTermRedirectWriteEvent())->setRedirect($redirect), TheliaEvents::SEARCH_TERM_REDIRECT_TOGGLE);

        $report = $this->importer()->importRedirects($this->csv("en_US;sale;url;/promotions\n"));

        self::assertSame(1, $report->updated);
        self::assertSame(0, SearchTermRedirectQuery::create()->findPk($redirect->getId())?->getActive());
    }

    public function testTheHeaderIsRecognisedAfterBlankLines(): void
    {
        $report = $this->importer()->importSynonyms($this->csv("\n\nlocale;term;synonyms;bidirectional\nen_US;tel;telephone;0\n"));

        self::assertSame(1, $report->created);
        self::assertSame([], $report->refused);
    }

    public function testTheImportRefusesAFileNotInUtf8(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->importer()->importSynonyms($this->csv(mb_convert_encoding("en_US;écharpe;étole;0\n", 'ISO-8859-1', 'UTF-8')));
    }

    public function testTheImportRefusesTooManyLines(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->importer()->importSynonyms($this->csv(str_repeat("x;y;z;0\n", SearchDictionaryImporter::MAX_ROWS + 1)));
    }

    public function testTheHeaderIsNotCountedAsALine(): void
    {
        $report = $this->importer()->importSynonyms($this->csv("locale;term;synonyms;bidirectional\n".str_repeat("x;y;z;0\n", SearchDictionaryImporter::MAX_ROWS)));

        self::assertCount(SearchDictionaryImporter::MAX_ROWS, $report->refused);
    }

    public function testTheImportRefusesATooLargeFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->importer()->importSynonyms($this->csv(str_repeat("en_US;tel;telephone;0\n", (int) (SearchDictionaryImporter::MAX_BYTES / 20))));
    }

    private function redirect(string $term, string $type, ?int $targetId = null, ?string $url = null): SearchTermRedirect
    {
        $event = (new SearchTermRedirectWriteEvent())
            ->setLangId($this->english()->getId())
            ->setTerm($term)
            ->setTargetType($type)
            ->setTargetId($targetId)
            ->setUrl($url);

        $this->dispatcher()->dispatch($event, TheliaEvents::SEARCH_TERM_REDIRECT_CREATE);

        return $event->getRedirect() ?? throw new \LogicException('The redirection was not saved.');
    }

    /**
     * @param list<string> $synonyms
     */
    private function synonym(string $term, array $synonyms): SearchSynonymWriteEvent
    {
        $event = (new SearchSynonymWriteEvent())
            ->setLangId($this->english()->getId())
            ->setTerm($term)
            ->setSynonyms($synonyms);

        $this->dispatcher()->dispatch($event, TheliaEvents::SEARCH_SYNONYM_CREATE);

        return $event;
    }

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dictionary');
        file_put_contents($path, $content);

        return $this->files[] = $path;
    }

    private function english(): Lang
    {
        return LangQuery::create()->findOneByLocale('en_US') ?? throw new \LogicException('en_US is missing.');
    }

    private function search(): SearchService
    {
        return static::getContainer()->get(SearchService::class);
    }

    private function importer(): SearchDictionaryImporter
    {
        return static::getContainer()->get(SearchDictionaryImporter::class);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
