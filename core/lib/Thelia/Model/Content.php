<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Content\ContentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Content as BaseContent;
use Thelia\Model\Map\ContentFolderTableMap;
use Thelia\Model\Map\ContentTableMap;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Content extends BaseContent implements FileModelParentInterface
{
    use ModelEventDispatcherTrait;

    use PositionManagementTrait;

    use UrlRewritingTrait;

    /**
     * {@inheritDoc}
     */
    public function getRewrittenUrlViewName()
    {
        return 'content';
    }

    /**
     * Calculate next position relative to our parent
     *
     * @param ContentQuery $query
     * @deprecated since 2.3, and will be removed in 2.4
     */
    protected function addCriteriaToPositionQuery($query)
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
     * @param int $defaultFolderId
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
     * @param $defaultFolderId
     *
     * @throws \Exception
     *
     * @return $this Return $this, allow chaining
     */
    public function create($defaultFolderId)
    {
        $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);

        $con->beginTransaction();

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATECONTENT, new ContentEvent($this));

        try {
            $this->save($con);

            $this->setDefaultFolder($defaultFolderId)->save($con);

            $con->commit();

            $this->dispatchEvent(TheliaEvents::AFTER_CREATECONTENT, new ContentEvent($this));
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }

        return $this;
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATECONTENT, new ContentEvent($this));

        return true;
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATECONTENT, new ContentEvent($this));
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETECONTENT, new ContentEvent($this));

        return true;
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        $this->markRewrittenUrlObsolete();

        $this->dispatchEvent(TheliaEvents::AFTER_DELETECONTENT, new ContentEvent($this));
    }

    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolder::setPosition
     */
    public function setPosition($v)
    {
        return parent::setPosition($v);
    }

    /**
     * @inheritdoc
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolder::getPosition
     */
    public function getPosition()
    {
        return parent::getPosition();
    }

    public function postSave(ConnectionInterface $con = null)
    {
        // For BC, will be removed in 2.4
        if (!$this->isNew()) {
            if (isset($this->modifiedColumns[ContentTableMap::POSITION]) && $this->modifiedColumns[ContentTableMap::POSITION]) {
                if (null !== $productCategory = ContentFolderQuery::create()
                        ->filterByContent($this)
                        ->filterByDefaultFolder(true)
                        ->findOne()
                ) {
                    $productCategory->changeAbsolutePosition($this->getPosition());
                }
            }
        }
    }

    /**
     * Overload for the position management
     * @param ContentFolder $contentFolder
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
