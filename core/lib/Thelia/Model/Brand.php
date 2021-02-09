<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Brand\BrandEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Files\FileModelParentInterface;
use Thelia\Model\Base\Brand as BaseBrand;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Brand extends BaseBrand implements FileModelParentInterface
{
    use PositionManagementTrait;

    use UrlRewritingTrait;

    /**
     * {@inheritDoc}
     */
    public function getRewrittenUrlViewName()
    {
        return 'brand';
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();
    }
}
