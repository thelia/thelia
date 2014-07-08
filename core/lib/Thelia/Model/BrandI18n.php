<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\BrandI18n as BaseBrandI18n;

class BrandI18n extends BaseBrandI18n
{
    public function postInsert(ConnectionInterface $con = null)
    {
        $content = $this->getBrand();

        $content->generateRewrittenUrl($this->getLocale());
    }
}
