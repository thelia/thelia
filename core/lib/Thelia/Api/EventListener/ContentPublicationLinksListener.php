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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\Content as ContentResource;
use Thelia\Api\Resource\Folder as FolderResource;
use Thelia\Api\Resource\Product as ProductResource;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Domain\CMS\Publication\ContentFolderPublicationRule;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ContentTableMap;

/**
 * Keeps the relations a front read serializes to what the shop publishes.
 *
 * `ContentPublicationExtension` closes the front endpoints of the contents, the folders
 * and what hangs from a content. The relations a resource carries are read by the
 * bridge from the model, outside any endpoint: a folder listed its drafts under
 * `contentFolders`, a content named the draft folders it is also filed in, a product
 * named the draft contents associated to it. Each of them was a way to learn which
 * drafts exist. Once a front resource is built, the links to a content the shop does
 * not show (hidden, or held by a default folder not online yet) and to a folder that is
 * not online are dropped, in one query per resource that carries links.
 *
 * An admin read keeps every link: a back-office user reads every state.
 */
final readonly class ContentPublicationLinksListener implements EventSubscriberInterface
{
    public function __construct(
        private ContentFolderPublicationRule $folderPublicationRule,
    ) {
    }

    public function keepTheLinksToWhatTheShopPublishes(ModelToResourceEvent $event): void
    {
        $resource = $event->getResource();
        $groups = self::groupsOf($event->getContext());

        if ($resource instanceof FolderResource && \in_array(FolderResource::GROUP_FRONT_READ, $groups, true)) {
            $resource->setContentFolders($this->linksToShownContents($resource->getContentFolders(), 'content'));

            return;
        }

        if ($resource instanceof ContentResource && \in_array(ContentResource::GROUP_FRONT_READ, $groups, true)) {
            $resource->setContentFolders($this->linksToPublishedFolders($resource->getContentFolders()));

            return;
        }

        if ($resource instanceof ProductResource && \in_array(ProductResource::GROUP_FRONT_READ_SINGLE, $groups, true)
            && (new \ReflectionProperty($resource, 'productAssociatedContents'))->isInitialized($resource)) {
            $resource->setProductAssociatedContents($this->linksToShownContents($resource->getProductAssociatedContents(), 'content'));
        }
    }

    /**
     * @param list<PropelResourceInterface> $links
     *
     * @return list<PropelResourceInterface>
     */
    private function linksToShownContents(array $links, string $contentProperty): array
    {
        $contentIds = self::relatedIds($links, $contentProperty);

        if ([] === $contentIds) {
            return $links;
        }

        $shown = ContentQuery::create()
            ->filterById($contentIds)
            ->filterByVisible(1)
            ->where($this->folderPublicationRule->outsideAnUnreleasedFolderClause(ContentTableMap::COL_ID))
            ->select(['Id'])
            ->find()
            ->getData();

        return self::keep($links, $contentProperty, array_map(intval(...), $shown));
    }

    /**
     * @param list<PropelResourceInterface> $links
     *
     * @return list<PropelResourceInterface>
     */
    private function linksToPublishedFolders(array $links): array
    {
        $folderIds = self::relatedIds($links, 'folder');

        if ([] === $folderIds) {
            return $links;
        }

        $published = FolderQuery::create()
            ->filterById($folderIds)
            ->filterByVisible(1)
            ->select(['Id'])
            ->find()
            ->getData();

        return self::keep($links, 'folder', array_map(intval(...), $published));
    }

    /**
     * @param list<PropelResourceInterface> $links
     * @param list<int>                     $ids
     *
     * @return list<PropelResourceInterface>
     */
    private static function keep(array $links, string $property, array $ids): array
    {
        return array_values(array_filter(
            $links,
            static fn (PropelResourceInterface $link): bool => \in_array(self::relatedId($link, $property), $ids, true),
        ));
    }

    /**
     * @param list<PropelResourceInterface> $links
     *
     * @return list<int>
     */
    private static function relatedIds(array $links, string $property): array
    {
        $ids = [];

        foreach ($links as $link) {
            $id = self::relatedId($link, $property);

            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The identifier of the resource at the other end of a link, null when the bridge did
     * not hydrate that end (its property is typed, and stays unset then).
     */
    private static function relatedId(PropelResourceInterface $link, string $property): ?int
    {
        $reflection = new \ReflectionProperty($link, $property);

        if (!$reflection->isInitialized($link)) {
            return null;
        }

        $related = $reflection->getValue($link);

        return $related instanceof PropelResourceInterface && method_exists($related, 'getId') ? $related->getId() : null;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<string>
     */
    private static function groupsOf(array $context): array
    {
        $groups = $context['groups'] ?? [];

        return array_values(\is_array($groups) ? $groups : [$groups]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['keepTheLinksToWhatTheShopPublishes', 0],
            ],
        ];
    }
}
