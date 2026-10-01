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

namespace Thelia\Core\Content\Slot;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Tools\I18n;
use Thelia\Tools\URL;

/**
 * The slots of the core, filled from contents and folders. Asked last, so that any module
 * owning a slot answers before it.
 *
 * - header_links: the references of the `header_menu_items` setting, in their order, written
 *   `folder:<id>,content:<id>`. A reference to a folder or a content that is missing or
 *   hidden is skipped, and so is anything that is not a reference. A folder comes without
 *   children: a theme builds its menu from the folder id.
 * - footer_links: the visible contents of the folder named by `information_folder_id`, in
 *   their position in that folder.
 * - consent.<code>: the content the consent links to, when it exists and is visible.
 *
 * Every other code is left to the other resolvers.
 */
#[AsTaggedItem(priority: -100)]
final readonly class NativeContentSlotResolver implements ContentSlotResolverInterface
{
    public const HEADER_MENU_ITEMS_CONFIG = 'header_menu_items';
    public const INFORMATION_FOLDER_CONFIG = 'information_folder_id';

    private const REFERENCE_PATTERN = '/^\s*(folder|content):(\d+)\s*$/';

    public function __construct(
        private URL $url,
    ) {
    }

    public function resolve(string $slot, string $locale): ?array
    {
        if (ContentSlots::HEADER === $slot) {
            return $this->headerLinks($locale);
        }

        if (ContentSlots::FOOTER === $slot) {
            return $this->footerLinks($locale);
        }

        if (str_starts_with($slot, ContentSlots::CONSENT_PREFIX)) {
            return $this->consentLinks(substr($slot, \strlen(ContentSlots::CONSENT_PREFIX)), $locale);
        }

        return null;
    }

    /**
     * @return list<ContentSlotLink>
     */
    private function headerLinks(string $locale): array
    {
        $links = [];

        foreach (explode(',', (string) ConfigQuery::read(self::HEADER_MENU_ITEMS_CONFIG, '')) as $reference) {
            if (1 !== preg_match(self::REFERENCE_PATTERN, $reference, $matches)) {
                continue;
            }

            $link = 'folder' === $matches[1]
                ? $this->folderLink((int) $matches[2], $locale)
                : $this->contentLink((int) $matches[2], $locale);

            if ($link instanceof ContentSlotLink) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * @return list<ContentSlotLink>
     */
    private function footerLinks(string $locale): array
    {
        $folderId = trim((string) ConfigQuery::read(self::INFORMATION_FOLDER_CONFIG, ''));

        if (!ctype_digit($folderId) || 0 === (int) $folderId) {
            return [];
        }

        $contents = ContentQuery::create()
            ->filterByVisible(1)
            ->useContentFolderQuery()
                ->filterByFolderId((int) $folderId)
                ->orderByPosition()
            ->endUse()
            ->orderById()
            ->find();

        $contentIds = array_map(static fn (Content $content): int => $content->getId(), $contents->getData());

        $this->url->preloadRewrittenUrls('content', $locale, $contentIds);

        return array_map(
            fn (int $contentId): ContentSlotLink => $this->linkTo('content', $contentId, $locale),
            $contentIds,
        );
    }

    /**
     * @return list<ContentSlotLink>
     */
    private function consentLinks(string $consentCode, string $locale): array
    {
        $contentId = ConsentQuery::create()->findOneByCode($consentCode)?->getContentId();

        if (null === $contentId) {
            return [];
        }

        $link = $this->contentLink($contentId, $locale);

        return $link instanceof ContentSlotLink ? [$link] : [];
    }

    private function folderLink(int $folderId, string $locale): ?ContentSlotLink
    {
        $visible = FolderQuery::create()->filterById($folderId)->filterByVisible(1)->exists();

        return $visible ? $this->linkTo('folder', $folderId, $locale) : null;
    }

    private function contentLink(int $contentId, string $locale): ?ContentSlotLink
    {
        $visible = ContentQuery::create()->filterById($contentId)->filterByVisible(1)->exists();

        return $visible ? $this->linkTo('content', $contentId, $locale) : null;
    }

    /**
     * @param 'folder'|'content' $source
     */
    private function linkTo(string $source, int $id, string $locale): ContentSlotLink
    {
        // The title in the asked locale, else in the default one; never the "DEFAULT TITLE"
        // placeholder I18n forges when it finds neither, hence the empty list of fields.
        $title = (string) I18n::forceI18nRetrieving($locale, ucfirst($source), $id, [])->getTitle();

        return new ContentSlotLink(
            label: $title,
            url: $this->url->retrieve($source, $id, $locale)->toString(),
            source: $source,
            sourceId: $id,
        );
    }
}
