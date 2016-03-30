<?php

namespace Thelia\Model;

use Thelia\Model\Base\ContentFolder as BaseContentFolder;

class ContentFolder extends BaseContentFolder
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * @inheritdoc
     */
    protected function addCriteriaToPositionQuery(ContentFolderQuery $query)
    {
        $query->filterByFolderId($this->getFolderId());
    }
}
