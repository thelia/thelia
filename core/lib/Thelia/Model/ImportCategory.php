<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\ImportCategory as BaseImportCategory;
use Thelia\Model\Map\ImportCategoryTableMap;

class ImportCategory extends BaseImportCategory
{
    public function upPosition()
    {
        if (($position = $this->getPosition()) > 1) {

            $previous = ImportCategoryQuery::create()
                ->findOneByPosition($position - 1)
            ;

            if (null !== $previous) {
                $previous->setPosition($position)->save();
            }

            $this->setPosition($position - 1)->save();
        }

        return $this;
    }

    public function downPosition()
    {
        $max = CategoryQuery::create()
            ->orderByPosition(Criteria::DESC)
            ->select(ImportCategoryTableMap::POSITION)
            ->findOne()
        ;

        $count = CategoryQuery::create()->count();

        if ($count > $max) {
            $max = $count;
        }

        $position = $this->getPosition();

        if ($position < $max) {

            $next = ImportCategoryQuery::create()
                ->findOneByPosition($position + 1)
            ;

            if (null !== $next) {
                $next->setPosition($position)->save();
            }

            $this->setPosition($position + 1)->save();
        }

        return $this;
    }

    public function updatePosition($position)
    {
        $reverse = ImportCategoryQuery::create()
            ->findOneByPosition($position)
        ;

        if (null !== $reverse) {
            $reverse->setPosition($this->getPosition())->save();
        }

        $this->setPosition($position)->save();
    }
}
