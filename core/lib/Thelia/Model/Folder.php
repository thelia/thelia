<?php

namespace Thelia\Model;

use Thelia\Model\Base\Folder as BaseFolder;
use Thelia\Tools\URL;
use Propel\Runtime\Connection\ConnectionInterface;

class Folder extends BaseFolder
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    use \Thelia\Model\Tools\UrlRewritingTrait;

    /**
     * {@inheritDoc}
     */
    protected function getRewritenUrlViewName() {
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

        foreach($children as $child)
        {
            $contentsCount += ProductQuery::create()
                ->filterByCategory($child)
                ->count();
        }

        return $contentsCount;

    }

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query) {
        $query->filterByParent($this->getParent());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        $this->generateRewritenUrl($this->getLocale());

        return true;
    }
}
