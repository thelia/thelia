<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\BrandI18n as BaseBrandI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class BrandI18n extends BaseBrandI18n
{
    use I18nTimestampableTrait;

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $brand = $this->getBrand();

        $brand->generateRewrittenUrl($this->getLocale(), $con);
    }
}
