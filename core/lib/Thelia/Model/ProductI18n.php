<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\ProductI18n as BaseProductI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class ProductI18n extends BaseProductI18n
{
    use I18nTimestampableTrait;

    public function postInsert(ConnectionInterface $con = null)
    {
        $product = $this->getProduct();
        $product->generateRewrittenUrl($this->getLocale());
    }
}
