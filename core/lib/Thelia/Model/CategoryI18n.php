<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\CategoryI18n as BaseCategoryI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class CategoryI18n extends BaseCategoryI18n
{
    use I18nTimestampableTrait;

    public function postInsert(ConnectionInterface $con = null)
    {
        $category = $this->getCategory();
        $category->generateRewrittenUrl($this->getLocale());
    }
}
