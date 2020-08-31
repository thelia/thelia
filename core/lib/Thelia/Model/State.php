<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\State\StateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\State as BaseState;

class State extends BaseState
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATESTATE, new StateEvent($this));

        return true;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $this->dispatchEvent(TheliaEvents::AFTER_CREATESTATE, new StateEvent($this));
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        parent::preUpdate($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATESTATE, new StateEvent($this));

        return true;
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        parent::postUpdate($con);

        $this->dispatchEvent(TheliaEvents::AFTER_UPDATESTATE, new StateEvent($this));
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_DELETESTATE, new StateEvent($this));

        return true;
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        parent::postDelete($con);

        $this->dispatchEvent(TheliaEvents::AFTER_DELETESTATE, new StateEvent($this));
    }
}
