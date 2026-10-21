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

namespace Thelia\Domain\CMS\Publication;

use Psr\Clock\ClockInterface;
use Thelia\Model\Map\ContentFolderTableMap;
use Thelia\Model\Map\FolderTableMap;

/**
 * A content whose default folder is a draft or is still waiting for its date is not
 * on the shop, whatever its own state: the folder is being prepared, and so is what it
 * holds. A folder taken offline keeps the behaviour an invisible folder always had: its
 * contents stay reachable by their own url.
 *
 * The rule is an SQL clause on the content id, so the content page and the front API
 * ask the same question in one query each.
 */
final readonly class ContentFolderPublicationRule
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /**
     * The clause a query on contents adds to leave out the contents of a folder that is
     * not online yet.
     */
    public function outsideAnUnreleasedFolderClause(string $contentIdColumn): string
    {
        return \sprintf(
            '%s NOT IN (SELECT %s FROM %s INNER JOIN %s ON %s = %s WHERE %s = 1 AND (%s IS NULL OR %s <> 1) AND (%s IS NULL OR %s > %s))',
            $contentIdColumn,
            ContentFolderTableMap::COL_CONTENT_ID,
            ContentFolderTableMap::TABLE_NAME,
            FolderTableMap::TABLE_NAME,
            FolderTableMap::COL_ID,
            ContentFolderTableMap::COL_FOLDER_ID,
            ContentFolderTableMap::COL_DEFAULT_FOLDER,
            FolderTableMap::COL_VISIBLE,
            FolderTableMap::COL_VISIBLE,
            FolderTableMap::COL_UNPUBLISH_AT,
            FolderTableMap::COL_UNPUBLISH_AT,
            $this->quotedNow(),
        );
    }

    private function quotedNow(): string
    {
        return "'".$this->clock->now()->format('Y-m-d H:i:s')."'";
    }
}
