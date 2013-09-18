<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\CategoryI18n as BaseCategoryI18n;

class CategoryI18n extends BaseCategoryI18n {
    public function postInsert(ConnectionInterface $con = null)
    {
        $category = $this->getCategory();
        $category->generateRewrittenUrl($this->getLocale());
    }
}
