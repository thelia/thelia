<?php

namespace Thelia\Model;

use Thelia\Model\Base\FeatureProduct as BaseFeatureProduct;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\FeatureProduct\FeatureProductEvent;
use Thelia\Log\Tlog;

class FeatureProduct extends BaseFeatureProduct
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEFEATURE_PRODUCT, new FeatureProductEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATEFEATURE_PRODUCT, new FeatureProductEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEFEATURE_PRODUCT, new FeatureProductEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEFEATURE_PRODUCT, new FeatureProductEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEFEATURE_PRODUCT, new FeatureProductEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEFEATURE_PRODUCT, new FeatureProductEvent($this));
    }

    /**
     * @deprecated
     */
     public function getFreeTextValue()
     {
         $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
         Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
         return parent::getFreeTextValue();
     }
}
