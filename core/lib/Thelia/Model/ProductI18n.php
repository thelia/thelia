<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\ProductI18n as BaseProductI18n;

class ProductI18n extends BaseProductI18n
{
    public function postInsert(ConnectionInterface $con = null)
    {
        $product = $this->getProduct();
        $product->generateRewrittenUrl($this->getLocale());
    }
}
