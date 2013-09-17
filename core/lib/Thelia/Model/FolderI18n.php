<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\FolderI18n as BaseFolderI18n;

class FolderI18n extends BaseFolderI18n {

    public function postInsert(ConnectionInterface $con = null)
    {
        $folder = $this->getFolder();
        $folder->generateRewrittenUrl($this->getLocale());
    }
}
