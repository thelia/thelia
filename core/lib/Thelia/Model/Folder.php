<?php

namespace Thelia\Model;

use Thelia\Core\Event\Folder\FolderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Folder as BaseFolder;

use Propel\Runtime\Connection\ConnectionInterface;

class Folder extends BaseFolder
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    use \Thelia\Model\Tools\UrlRewritingTrait;

    /**
     * {@inheritDoc}
     */
    protected function getRewrittenUrlViewName()
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
     *
     * count all products for current category and sub categories
     *
     * @return int
     */
    public function countAllContents()
    {
        $children = FolderQuery::findAllChild($this->getId());
        array_push($children, $this);

        $contentsCount = 0;

        foreach ($children as $child) {
            $contentsCount += ContentQuery::create()
                ->filterByFolder($child)
                ->count();
        }

        return $contentsCount;

    }

    /**
     * Calculate next position relative to our parent
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
        $this->setPosition($this->getNextPosition());

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEFOLDER, new FolderEvent($this));

        return true;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATEFOLDER, new FolderEvent($this));
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEFOLDER, new FolderEvent($this));

        return true;
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEFOLDER, new FolderEvent($this));
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEFOLDER, new FolderEvent($this));
        $this->reorderBeforeDelete(
            array(
                "parent" => $this->getParent(),
            )
        );

        return true;
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        $this->markRewritenUrlObsolete();

        $this->dispatchEvent(TheliaEvents::AFTER_DELETEFOLDER, new FolderEvent($this));
    }
}
