<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Area\AreaEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Area as BaseArea;

class Area extends BaseArea
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEAREA, new AreaEvent($this));

        return true;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATEAREA, new AreaEvent($this));
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEAREA, new AreaEvent($this));

        return true;
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEAREA, new AreaEvent($this));
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEAREA, new AreaEvent($this));

        return true;
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEAREA, new AreaEvent($this));
    }
}
