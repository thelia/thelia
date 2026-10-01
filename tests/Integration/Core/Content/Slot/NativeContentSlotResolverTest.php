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

namespace Thelia\Tests\Integration\Core\Content\Slot;

use Thelia\Core\Content\Slot\ContentSlotLink;
use Thelia\Core\Content\Slot\ContentSlots;
use Thelia\Core\Content\Slot\ContentSlotService;
use Thelia\Core\Content\Slot\NativeContentSlotResolver;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\Folder;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\URL;

/**
 * The slots the core fills from contents and folders, read through the service a theme
 * calls, with the resolvers the container registers.
 */
final class NativeContentSlotResolverTest extends IntegrationTestCase
{
    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new FixtureFactory($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // The settings written here are rolled back, the memo of ConfigQuery is not.
        ConfigQuery::resetCache();
    }

    public function testTheCoreResolverIsRegisteredAndAskedLast(): void
    {
        $resolvers = iterator_to_array($this->resolversOf($this->service()), false);

        self::assertNotEmpty($resolvers);
        self::assertInstanceOf(NativeContentSlotResolver::class, end($resolvers));
    }

    public function testTheHeaderListsTheReferencedFolderAndContentInTheirOrder(): void
    {
        $folder = $this->fixtures->folder(0, ['title' => 'Blog']);
        $content = $this->fixtures->content($folder, ['title' => 'About us']);

        ConfigQuery::write(NativeContentSlotResolver::HEADER_MENU_ITEMS_CONFIG, \sprintf('content:%d,folder:%d', $content->getId(), $folder->getId()));

        $links = $this->service()->links(ContentSlots::HEADER, 'en_US');

        self::assertEquals(
            [
                new ContentSlotLink(label: 'About us', url: $this->urlOf('content', $content->getId()), source: 'content', sourceId: $content->getId()),
                new ContentSlotLink(label: 'Blog', url: $this->urlOf('folder', $folder->getId()), source: 'folder', sourceId: $folder->getId()),
            ],
            $links,
        );
        self::assertSame([], $links[1]->children, 'a folder comes without children: the theme builds its menu from the id');
    }

    public function testTheHeaderSkipsHiddenMissingAndMalformedReferences(): void
    {
        $folder = $this->fixtures->folder(0, ['title' => 'Blog']);
        $hiddenFolder = $this->fixtures->folder(0, ['visible' => 0]);
        $hiddenContent = $this->fixtures->content($folder, ['visible' => 0]);
        $missingContentId = $hiddenContent->getId() + 1000;

        ConfigQuery::write(
            NativeContentSlotResolver::HEADER_MENU_ITEMS_CONFIG,
            \sprintf(
                'folder:%d, content:%d,folder:%d,content:%d,page:1,folder:x,,folder:,folder:%dx',
                $hiddenFolder->getId(),
                $hiddenContent->getId(),
                $folder->getId(),
                $missingContentId,
                $folder->getId(),
            ),
        );

        self::assertSame(
            [['folder', $folder->getId()]],
            $this->sources($this->service()->links(ContentSlots::HEADER, 'en_US')),
        );
    }

    public function testAnEmptyHeaderSettingGivesAnEmptySlot(): void
    {
        ConfigQuery::write(NativeContentSlotResolver::HEADER_MENU_ITEMS_CONFIG, '');

        self::assertSame([], $this->resolver()->resolve(ContentSlots::HEADER, 'en_US'));
    }

    public function testTheLabelIsTheTitleInTheAskedLocaleThenInTheDefaultOne(): void
    {
        $folder = $this->fixtures->folder(0, ['title' => 'Blog']);
        $folder->setLocale('fr_FR')->setTitle('Le blog')->save();
        $content = $this->fixtures->content($folder, ['title' => 'About us']);

        ConfigQuery::write(NativeContentSlotResolver::HEADER_MENU_ITEMS_CONFIG, \sprintf('folder:%d,content:%d', $folder->getId(), $content->getId()));

        $links = $this->service()->links(ContentSlots::HEADER, 'fr_FR');

        self::assertSame(['Le blog', 'About us'], array_map(static fn (ContentSlotLink $link): string => $link->label, $links));
        self::assertSame($this->urlOf('folder', $folder->getId(), 'fr_FR'), $links[0]->url);
    }

    public function testTheFooterListsTheVisibleContentsOfTheInformationFolderByPosition(): void
    {
        $information = $this->fixtures->folder();
        $other = $this->fixtures->folder();
        $terms = $this->fixtures->content($information, ['title' => 'Terms']);
        $delivery = $this->fixtures->content($information, ['title' => 'Delivery']);
        $hidden = $this->fixtures->content($information, ['visible' => 0]);
        $elsewhere = $this->fixtures->content($other, ['title' => 'Elsewhere']);
        $alsoHere = $this->fixtures->content($other, ['title' => 'Imprint']);
        (new ContentFolder())->setContentId($alsoHere->getId())->setFolderId($information->getId())->setDefaultFolder(false)->setPosition(1)->save();

        $this->placeInFolder($information, [$alsoHere, $hidden, $delivery, $terms]);

        ConfigQuery::write(NativeContentSlotResolver::INFORMATION_FOLDER_CONFIG, (string) $information->getId());

        self::assertSame(
            [['content', $alsoHere->getId()], ['content', $delivery->getId()], ['content', $terms->getId()]],
            $this->sources($this->service()->links(ContentSlots::FOOTER, 'en_US')),
        );
        self::assertNotContains($elsewhere->getId(), array_column($this->sources($this->service()->links(ContentSlots::FOOTER, 'en_US')), 1));
    }

    public function testAnEmptyInformationFolderSettingGivesAnEmptySlot(): void
    {
        ConfigQuery::write(NativeContentSlotResolver::INFORMATION_FOLDER_CONFIG, '');

        self::assertSame([], $this->resolver()->resolve(ContentSlots::FOOTER, 'en_US'));
    }

    public function testAConsentSlotLinksToTheVisibleContentOfTheConsent(): void
    {
        $folder = $this->fixtures->folder();
        $terms = $this->fixtures->content($folder, ['title' => 'Terms and Conditions']);
        $this->termsConsent()->setContentId($terms->getId())->save();

        self::assertEquals(
            [new ContentSlotLink(label: 'Terms and Conditions', url: $this->urlOf('content', $terms->getId()), source: 'content', sourceId: $terms->getId())],
            $this->service()->links('consent.terms_and_conditions', 'en_US'),
        );
    }

    public function testAConsentSlotIsEmptyWhenItsContentIsHidden(): void
    {
        $folder = $this->fixtures->folder();
        $terms = $this->fixtures->content($folder, ['visible' => 0]);
        $this->termsConsent()->setContentId($terms->getId())->save();

        self::assertSame([], $this->resolver()->resolve('consent.terms_and_conditions', 'en_US'));
        self::assertNull($this->service()->first('consent.terms_and_conditions', 'en_US'));
    }

    public function testAConsentSlotIsEmptyWithoutContentOrWithoutConsent(): void
    {
        $this->termsConsent()->setContentId(null)->save();

        self::assertSame([], $this->resolver()->resolve('consent.terms_and_conditions', 'en_US'));
        self::assertSame([], $this->resolver()->resolve('consent.no_such_consent', 'en_US'));
    }

    public function testAnUnknownSlotIsLeftToTheOtherResolvers(): void
    {
        self::assertNull($this->resolver()->resolve('sidebar_links', 'en_US'));
        self::assertSame([], $this->service()->links('sidebar_links', 'en_US'));
    }

    /**
     * @param list<Content> $contents in the order they take in the folder
     */
    private function placeInFolder(Folder $folder, array $contents): void
    {
        foreach ($contents as $index => $content) {
            ContentFolderQuery::create()
                ->filterByFolderId($folder->getId())
                ->filterByContentId($content->getId())
                ->update(['Position' => $index + 1]);
        }
    }

    private function termsConsent(): Consent
    {
        $consent = ConsentQuery::create()->findOneByCode(Consent::CODE_TERMS_AND_CONDITIONS);
        self::assertNotNull($consent, 'the seed creates the terms and conditions consent');

        return $consent;
    }

    /**
     * @param list<ContentSlotLink> $links
     *
     * @return list<array{string, int|string|null}>
     */
    private function sources(array $links): array
    {
        return array_map(static fn (ContentSlotLink $link): array => [$link->source, $link->sourceId], $links);
    }

    private function urlOf(string $view, int $id, string $locale = 'en_US'): string
    {
        return URL::getInstance()->retrieve($view, $id, $locale)->toString();
    }

    private function service(): ContentSlotService
    {
        return $this->getService(ContentSlotService::class);
    }

    private function resolver(): NativeContentSlotResolver
    {
        return $this->getService(NativeContentSlotResolver::class);
    }

    /**
     * @return iterable<mixed>
     */
    private function resolversOf(ContentSlotService $service): iterable
    {
        $resolvers = (new \ReflectionProperty(ContentSlotService::class, 'resolvers'))->getValue($service);
        self::assertIsIterable($resolvers);

        return $resolvers;
    }
}
