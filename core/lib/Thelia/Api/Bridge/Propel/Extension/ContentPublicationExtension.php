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
use Thelia\Api\Resource\Folder;
use Thelia\Domain\CMS\Publication\ContentFolderPublicationRule;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ContentTableMap;

/**
 * Keeps the front content and folder endpoints to what the shop publishes.
 *
 * A draft, a scheduled row and a row taken offline are hidden, whatever `visible` filter
 * the caller passes: the filter is the caller's, this rule is the shop's. A content whose
 * default folder is not online yet is hidden with it, as on the content page. The rule is
 * applied in the query, so the collection and the item read answer the same and a row
 * out of reach answers 404 rather than an empty-looking 200.
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
        }
    }
}
