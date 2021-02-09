<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\FolderI18n as BaseFolderI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class FolderI18n extends BaseFolderI18n
{
    use I18nTimestampableTrait;

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $folder = $this->getFolder();
        $folder->generateRewrittenUrl($this->getLocale(), $con);
    }
}
