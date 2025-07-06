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
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Content as BaseContent;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Content extends BaseContent implements FileModelParentInterface
{
    use PositionManagementTrait;
    use UrlRewritingTrait;

    public function getRewrittenUrlViewName()
    {
        return 'content';
    }

    /**
     * Calculate next position relative to our parent.
     *
     * @param ContentQuery $query
     *
     * @deprecated since 2.3, and will be removed in 2.4
     */
    protected function addCriteriaToPositionQuery($query): void
    {
        $contents = ContentFolderQuery::create()
            ->filterByFolderId($this->getDefaultFolderId())
            ->filterByDefaultFolder(true)
            ->select('content_id')
            ->find();

        // Filtrer la requete sur ces produits
        if ($contents != null) {
            $query->filterById($contents, Criteria::IN);
        }
    }

    /**
     * @return int
     */
    public function getDefaultFolderId()
    {
        // Find default folder
        $default_folder = ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->filterByDefaultFolder(true)
            ->findOne();

        return $default_folder == null ? 0 : $default_folder->getFolderId();
    }

    /**
     * @param int $defaultFolderId
     *
     * @return $this
     */
    public function setDefaultFolder($defaultFolderId)
    {
        // Allow uncategorized content (NULL instead of 0, to bypass delete cascade constraint)
        if ($defaultFolderId <= 0) {
            $defaultFolderId = null;
        }

        $contentFolder = ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->filterByDefaultFolder(true)
            ->findOne();

        if ($contentFolder !== null && (int) $contentFolder->getFolderId() === (int) $defaultFolderId) {
            return $this;
        }

        if ($contentFolder !== null) {
            $contentFolder->delete();
        }

        // checks if the content is already associated with the folder and but not default
        if (null !== $contentFolder = ContentFolderQuery::create()->filterByContent($this)->filterByFolderId($defaultFolderId)->findOne()) {
            $contentFolder->setDefaultFolder(true)->save();
        } else {
            $position = (new ContentFolder())->setFolderId($defaultFolderId)->getNextPosition();

            (new ContentFolder())
                ->setContent($this)
                ->setFolderId($defaultFolderId)
                ->setDefaultFolder(true)
                ->setPosition($position)
                ->save();

            // For BC, will be removed in 2.4
            $this->setPosition($position);
        }

        return $this;
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use Content::setDefaultFolder
     *
     * @param int $defaultFolderId
     *
     * @return $this
     */
    public function updateDefaultFolder($defaultFolderId)
    {
        return $this->setDefaultFolder($defaultFolderId);
    }

    /**
     * Create a new content.
     *
     * Here pre and post insert event are fired
     *
     * @throws \Exception
     *
     * @return $this Return $this, allow chaining
     */
    public function create($defaultFolderId)
    {
        $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);

        $con->beginTransaction();

        try {
            $this->save($con);

            $this->setDefaultFolder($defaultFolderId)->save($con);

            $con->commit();
        } catch (\Exception $exception) {
            $con->rollback();

            throw $exception;
        }

        return $this;
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolder::setPosition
     */
    public function setPosition($v)
    {
        return parent::setPosition($v);
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolder::getPosition
     */
    public function getPosition()
    {
        return parent::getPosition();
    }

    public function postSave(?ConnectionInterface $con = null): void
    {
        // For BC, will be removed in 2.4
        if (!$this->isNew() && (isset($this->modifiedColumns[ContentTableMap::COL_POSITION]) && $this->modifiedColumns[ContentTableMap::COL_POSITION]) && null !== $productCategory = ContentFolderQuery::create()
                ->filterByContent($this)
                ->filterByDefaultFolder(true)
                ->findOne()) {
            $productCategory->changeAbsolutePosition($this->getPosition());
        }

        parent::postSave();
    }

    /**
     * Overload for the position management.
     *
     * @param ContentFolder $contentFolder
     */
    protected function doAddContentFolder($contentFolder): void
    {
        parent::doAddContentFolder($contentFolder);

        $contentFolderPosition = ContentFolderQuery::create()
            ->filterByFolderId($contentFolder->getFolderId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $contentFolder->setPosition($contentFolderPosition !== null ? $contentFolderPosition->getPosition() + 1 : 1);
    }
}
