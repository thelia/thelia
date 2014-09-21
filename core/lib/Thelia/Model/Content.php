<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Content\ContentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Content as BaseContent;

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

    public function getDefaultFolderId()
    {
        // Find default folder
        $default_folder = ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->filterByDefaultFolder(true)
            ->findOne();

        return $default_folder == null ? 0 : $default_folder->getFolderId();
    }

    public function setDefaultFolder($folderId)
    {
        // Unset previous category
        ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->filterByDefaultFolder(true)
            ->find()
            ->setByDefault(false)
            ->save();

        // Set new default category
        ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->filterByFolderId($folderId)
            ->find()
            ->setByDefault(true)
            ->save();

        return $this;
    }

    public function updateDefaultFolder($defaultFolderId)
    {
        // Allow uncategorized content (NULL instead of 0, to bypass delete cascade constraint)
        if ($defaultFolderId <= 0) {
            $defaultFolderId = null;
        }

        if ($defaultFolderId == $this->getDefaultFolderId()) {
            return;
        }

        ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->update(array('DefaultFolder' => 0));

        $contentFolder = ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->filterByFolderId($defaultFolderId)
            ->findOne();

        if (null === $contentFolder) {
            $contentFolder = new ContentFolder();

            $contentFolder->setContentId($this->getId())
                ->setFolderId($defaultFolderId);
        }

        $contentFolder->setDefaultFolder(true)
            ->save();
    }

    /**
     * Create a new content.
     *
     * Here pre and post insert event are fired
     *
     * @param $defaultFolderId
     * @throws \Exception
     */
    public function create($defaultFolderId)
    {
        $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);

        $con->beginTransaction();

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATECONTENT, new ContentEvent($this));

        try {
            $this->save($con);

            $cf = new ContentFolder();
            $cf->setContentId($this->getId())
                ->setFolderId($defaultFolderId)
                ->setDefaultFolder(1)
                ->save($con);

            $this->setPosition($this->getNextPosition())->save($con);

            $con->commit();

            $this->dispatchEvent(TheliaEvents::AFTER_CREATECONTENT,new ContentEvent($this));
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }
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
}
