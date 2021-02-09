<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\FeatureProduct\FeatureProductEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\Base\FeatureProduct as BaseFeatureProduct;

class FeatureProduct extends BaseFeatureProduct
{
    /**
     * @inheritdoc
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  getIsFreeText() instead
     */
     public function getFreeTextValue()
     {
         $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
         Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
         return parent::getFreeTextValue();
     }

     /**
      * @inheritdoc
      * @deprecated since version 2.4.0, to be removed in 3.0.
      *                      Please use  setIsFreeText() instead
      */
     public function setFreeTextValue($v)
     {
         $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
         Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
         $this->setIsFreeText($v ? true : false);
         return parent::setFreeTextValue($v);
     }

     /**
      * {@inheritDoc}
      */
     public function setIsFreeText($v)
     {
         parent::setFreeTextValue($v ? 1 : null); //for preventing log deprecation and infinity recursion
         return parent::setIsFreeText($v);
     }
}
