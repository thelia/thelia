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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Folder as BaseFolder;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Folder extends BaseFolder implements FileModelParentInterface
{
    use PositionManagementTrait;
    use UrlRewritingTrait;

    public function getRewrittenUrlViewName()
    {
        return 'folder';
    }

    /**
     * @return int number of contents for the folder
     */
    public function countChild(): int
    {
        return FolderQuery::countChild($this->getId());
    }

    /**
     * count all products for current category and sub categories.
     */
    public function countAllContents($contentVisibility = true): int
    {
        $children = FolderQuery::findAllChild($this->getId());
        $children[] = $this;

        $query = ContentQuery::create()->filterByFolder(new ObjectCollection($children), Criteria::IN);

        if ('*' !== $contentVisibility) {
            $query->filterByVisible($contentVisibility);
        }

        return $query->count();
    }

    /**
     * Get the root folder.
     */
    public function getRoot(int $folderId)
    {
        $folder = FolderQuery::create()->findPk($folderId);

        if (0 !== $folder->getParent()) {
            $parentFolder = FolderQuery::create()->findPk($folder->getParent());

            if (null !== $parentFolder) {
                $folderId = $this->getRoot($parentFolder->getId());
            }
        }

        return $folderId;
    }

    /**
     * Calculate next position relative to our parent.
     */
    protected function addCriteriaToPositionQuery(FolderQuery $query): void
    {
        $query->filterByParent($this->getParent());
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function preDelete(?ConnectionInterface $con = null): bool
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'parent' => $this->getParent(),
            ],
        );

        return true;
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();
    }

    /**
     * Overload for the position management.
     */
    protected function doAddContentFolder(Base\ContentFolder $contentFolder): void
    {
        parent::doAddContentFolder($contentFolder);

        $contentFolderPosition = ContentFolderQuery::create()
            ->filterByFolderId($contentFolder->getFolderId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $contentFolder->setPosition(null !== $contentFolderPosition ? $contentFolderPosition->getPosition() + 1 : 1);
    }
}
