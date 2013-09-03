<?php

namespace Thelia\Model;

use Thelia\Model\Base\Folder as BaseFolder;
use Thelia\Tools\URL;

class Folder extends BaseFolder
{
    /**
     * @return int number of contents for the folder
     */
    public function countChild()
    {
        return FolderQuery::countChild($this->getId());
    }

    public function getUrl($locale)
    {
        return URL::init()->retrieve('folder', $this->getId(), $locale);
    }

    /**
     *
     * count all products for current category and sub categories
     *
     * @return int
     */
    public function countAllContents()
    {
        $children = FolderQuery::findAllChild($this->getId());
        array_push($children, $this);

        $contentsCount = 0;

        foreach($children as $child)
        {
            $contentsCount += ProductQuery::create()
                ->filterByCategory($child)
                ->count();
        }

        return $contentsCount;

    }
}
