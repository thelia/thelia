<?php

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
use Thelia\Core\Event\Folder\FolderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Folder as BaseFolder;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Folder extends BaseFolder implements FileModelParentInterface
{
    use PositionManagementTrait;

    use UrlRewritingTrait;

    /**
     * {@inheritDoc}
     */
    public function getRewrittenUrlViewName()
    {
        return 'folder';
    }

    /**
     * @return int number of contents for the folder
     */
    public function countChild()
    {
        return FolderQuery::countChild($this->getId());
    }

    /**
     * count all products for current category and sub categories
     *
     * @return int
     */
    public function countAllContents($contentVisibility = true)
    {
        $children = FolderQuery::findAllChild($this->getId());
        array_push($children, $this);

        $query = ContentQuery::create()->filterByFolder(new ObjectCollection($children), Criteria::IN);

        if ($contentVisibility !== '*') {
            $query->filterByVisible($contentVisibility);
        }

        return $query->count();
    }

    /**
     * Get the root folder
     * @param  int   $folderId
     */
    public function getRoot($folderId)
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
     * Calculate next position relative to our parent

     * @param FolderQuery $query
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByParent($this->getParent());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                "parent" => $this->getParent(),
            ]
        );

        return true;
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();
    }

    /**
     * Overload for the position management
     * @param Base\ContentFolder $contentFolder
     * @inheritdoc
     */
    protected function doAddContentFolder($contentFolder)
    {
        parent::doAddContentFolder($contentFolder);

        $contentFolderPosition = ContentFolderQuery::create()
            ->filterByFolderId($contentFolder->getFolderId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $contentFolder->setPosition($contentFolderPosition !== null ? $contentFolderPosition->getPosition() + 1 : 1);
    }
}
