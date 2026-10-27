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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\Content;
use Thelia\Api\Resource\ContentDocument;
use Thelia\Api\Resource\ContentFolder;
use Thelia\Api\Resource\ContentImage;
use Thelia\Api\Resource\Folder;
use Thelia\Api\Resource\Product;
use Thelia\Api\Resource\ProductAssociatedContent;
use Thelia\Domain\CMS\Publication\ContentFolderPublicationRule;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ContentDocumentTableMap;
use Thelia\Model\Map\ContentFolderTableMap;
use Thelia\Model\Map\ContentImageTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\ProductAssociatedContentTableMap;

/**
 * Keeps the front content and folder endpoints to what the shop publishes.
 *
 * A draft, a scheduled row and a row taken offline are hidden, whatever `visible` filter
 * the caller passes: the filter is the caller's, this rule is the shop's. A content whose
 * default folder is not online yet is hidden with it, as on the content page. The rule is
 * applied in the query, so the collection and the item read answer the same and a row
 * out of reach answers 404 rather than an empty-looking 200. What hangs from a content
 * follows it: its link to a folder, which holds nothing but identifiers and was a way
 * to learn which drafts exist; its images and documents, and the files behind them,
 * which are the draft itself; its association to a product. The relations a content or
 * a folder serializes are pruned the same way by `ContentPublicationLinksListener`.
 *
 * The admin endpoints are left alone: a back-office user reads every state, that is the
 * point of the screen.
 */
final readonly class ContentPublicationExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private ContentFolderPublicationRule $folderPublicationRule,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToWhatTheShopPublishes($query, $resourceClass, $operation);
        $this->scopeTheFilteredLinksToWhatTheShopPublishes($query, $resourceClass, $operation, $context['filters'] ?? []);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToWhatTheShopPublishes($query, $resourceClass, $operation);
    }

    private function scopeToWhatTheShopPublishes(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!str_starts_with((string) $operation?->getUriTemplate(), '/front/')) {
            return;
        }

        if (Content::class === $resourceClass && $query instanceof ContentQuery) {
            $query
                ->filterByVisible(1)
                ->where($this->folderPublicationRule->outsideAnUnreleasedFolderClause(ContentTableMap::COL_ID));

            return;
        }

        if (Folder::class === $resourceClass && $query instanceof FolderQuery) {
            $query->filterByVisible(1);

            return;
        }

        $contentIdColumn = match ($resourceClass) {
            ContentFolder::class => ContentFolderTableMap::COL_CONTENT_ID,
            ContentImage::class => ContentImageTableMap::COL_CONTENT_ID,
            ContentDocument::class => ContentDocumentTableMap::COL_CONTENT_ID,
            ProductAssociatedContent::class => ProductAssociatedContentTableMap::COL_CONTENT_ID,
            default => null,
        };

        if (null !== $contentIdColumn) {
            $query->where($this->ofAPublishedContentClause($contentIdColumn));
        }

        // A link names its folder too: one not online would tell the shop it exists.
        if (ContentFolder::class === $resourceClass) {
            $query->where(\sprintf(
                '%s IN (SELECT %s FROM %s WHERE %s = 1)',
                ContentFolderTableMap::COL_FOLDER_ID,
                FolderTableMap::COL_ID,
                FolderTableMap::TABLE_NAME,
                FolderTableMap::COL_VISIBLE,
            ));
        }
    }

    /**
     * A filter through a link answers whether the row it names exists: folders filtered
     * on a draft content, products on a draft associated content, contents on a draft
     * folder would each tell the draft is there. The joined link is held to the rows the
     * shop publishes, under the alias the eager loading extension gives it, which the
     * search filter names too.
     *
     * @param array<string, mixed> $filters
     */
    private function scopeTheFilteredLinksToWhatTheShopPublishes(ModelCriteria $query, string $resourceClass, ?Operation $operation, array $filters): void
    {
        if (!str_starts_with((string) $operation?->getUriTemplate(), '/front/')) {
            return;
        }

        [$relation, $clause] = match ($resourceClass) {
            Folder::class => ['contentFolders', $this->ofAPublishedContentClause('folder_contentfolders.content_id')],
            Product::class => ['productAssociatedContents', $this->ofAPublishedContentClause('product_productassociatedcontents.content_id')],
            Content::class => ['contentFolders', \sprintf(
                'content_contentfolders.folder_id IN (SELECT %s FROM %s WHERE %s = 1)',
                FolderTableMap::COL_ID,
                FolderTableMap::TABLE_NAME,
                FolderTableMap::COL_VISIBLE,
            )],
            default => [null, null],
        };

        if (null === $relation) {
            return;
        }

        foreach (array_keys($filters) as $filter) {
            if (str_starts_with((string) $filter, $relation.'.')) {
                $query->where($clause);

                return;
            }
        }
    }

    /**
     * The clause that keeps the rows hanging from a content the shop publishes.
     */
    private function ofAPublishedContentClause(string $contentIdColumn): string
    {
        return \sprintf(
            '%s IN (SELECT %s FROM %s WHERE %s = 1 AND %s)',
            $contentIdColumn,
            ContentTableMap::COL_ID,
            ContentTableMap::TABLE_NAME,
            ContentTableMap::COL_VISIBLE,
            $this->folderPublicationRule->outsideAnUnreleasedFolderClause(ContentTableMap::COL_ID),
        );
    }
}
