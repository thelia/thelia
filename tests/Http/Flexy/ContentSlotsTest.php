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

namespace Thelia\Tests\Http\Flexy;

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Core\Content\Slot\NativeContentSlotResolver;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\ThemeContentSlots;

/**
 * The header and the footer of the theme name no content and no folder: they show the
 * links of the header_links and footer_links content slots, which the shop fills through
 * its settings (or a module, through its own resolver).
 */
final class ContentSlotsTest extends WebIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!ThemeContentSlots::areReadByTheTheme()) {
            self::markTestSkipped('The installed theme reads its header and footer from content ids.');
        }
    }

    public function testTheHeaderShowsTheEntriesOfTheHeaderSettingInTheirOrder(): void
    {
        $fixtures = $this->fixtures();
        $folder = $fixtures->folder(0, ['title' => 'Slot folder']);
        $fixtures->content($folder, ['title' => 'Slot folder article']);
        $content = $fixtures->content($fixtures->folder(), ['title' => 'Slot content']);
        $hidden = $fixtures->content($fixtures->folder(), ['title' => 'Slot hidden content', 'visible' => 0]);

        ConfigQuery::write(
            NativeContentSlotResolver::HEADER_MENU_ITEMS_CONFIG,
            \sprintf('content:%d,content:%d,folder:%d', $content->getId(), $hidden->getId(), $folder->getId()),
        );

        $entries = $this->headerEntries($this->requestTheHomePage());

        self::assertSame(['Slot content', 'Slot folder'], \array_slice(array_keys($entries), -2), 'The header must end with the entries of the setting, in their order, the hidden one left out.');
        self::assertSame($content->getUrl('en_US'), $entries['Slot content']->filter('a.HeaderMenuItem-link')->attr('href'));
        self::assertStringContainsString(
            'Slot folder article',
            $entries['Slot folder']->filter('.Submenu')->text(''),
            'A folder must open on the mega-menu of its contents, as the theme drew it before.',
        );
    }

    public function testTheHeaderShowsNoContentEntryWhenTheSettingIsEmpty(): void
    {
        ConfigQuery::write(NativeContentSlotResolver::HEADER_MENU_ITEMS_CONFIG, '');

        $entries = $this->headerEntries($this->requestTheHomePage());

        $rootCategories = array_map(
            static fn (Category $category): string => (string) $category->setLocale('en_US')->getTitle(),
            CategoryQuery::create()->filterByParent(0)->filterByVisible(1)->find($this->getPropelConnection())->getData(),
        );

        self::assertEqualsCanonicalizing($rootCategories, array_keys($entries), 'Only the categories may remain in the header.');
    }

    public function testTheFooterListsTheVisibleContentsOfTheInformationFolder(): void
    {
        $fixtures = $this->fixtures();
        $folder = $fixtures->folder(0, ['title' => 'Slot information']);
        $legal = $fixtures->content($folder, ['title' => 'Slot legal notice']);
        $fixtures->content($folder, ['title' => 'Slot hidden notice', 'visible' => 0]);

        ConfigQuery::write(NativeContentSlotResolver::INFORMATION_FOLDER_CONFIG, (string) $folder->getId());

        $links = $this->requestTheHomePage()->filter('footer .Footer-links a');

        self::assertSame(['Slot legal notice'], $links->each(static fn (Crawler $link): string => trim($link->text())));
        self::assertSame($legal->getUrl('en_US'), $links->attr('href'));
    }

    private function requestTheHomePage(): Crawler
    {
        $crawler = $this->client->request('GET', '/');

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The home page must be served with a 200.');

        return $crawler;
    }

    /**
     * @return array<string, Crawler> the entries of the main navigation, by title
     */
    private function headerEntries(Crawler $crawler): array
    {
        $entries = [];

        $crawler->filter('.Header-navigation > li')->each(static function (Crawler $entry) use (&$entries): void {
            $entries[trim($entry->filter('.HeaderMenuItem-link')->first()->text())] = $entry;
        });

        return $entries;
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request that
     * would then stand for the main request of the page asked for below.
     */
    private function fixtures(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
