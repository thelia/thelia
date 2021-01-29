<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\ContentI18n as BaseContentI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class ContentI18n extends BaseContentI18n
{
    use I18nTimestampableTrait;

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $content = $this->getContent();
        $content->generateRewrittenUrl($this->getLocale(), $con);
    }
}
