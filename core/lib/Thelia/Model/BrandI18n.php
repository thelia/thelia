<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\BrandI18n as BaseBrandI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class BrandI18n extends BaseBrandI18n
{
    use I18nTimestampableTrait;

    public function postInsert(ConnectionInterface $con = null)
    {
        $brand = $this->getBrand();

        $brand->generateRewrittenUrl($this->getLocale());
    }
}
