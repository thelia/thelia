<?php

namespace Thelia\Model;

use Thelia\Model\Base\Template as BaseTemplate;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Template\TemplateEvent;
use Thelia\Core\Event\TheliaEvents;

class Template extends BaseTemplate
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATETEMPLATE, new TemplateEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $this->dispatchEvent(TheliaEvents::AFTER_CREATETEMPLATE, new TemplateEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        parent::preUpdate($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATETEMPLATE, new TemplateEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        parent::postUpdate($con);

        $this->dispatchEvent(TheliaEvents::AFTER_UPDATETEMPLATE, new TemplateEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_DELETETEMPLATE, new TemplateEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        parent::postDelete($con);

        $this->dispatchEvent(TheliaEvents::AFTER_DELETETEMPLATE, new TemplateEvent($this));
    }
}
