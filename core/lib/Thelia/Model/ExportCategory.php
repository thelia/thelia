<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\Base\ExportCategory as BaseExportCategory;
use Thelia\Model\Map\ExportCategoryTableMap;

class ExportCategory extends BaseExportCategory
{
    public function upPosition()
    {
        if (($position = $this->getPosition()) > 1) {

            $previous = ExportCategoryQuery::create()
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
            ->select(ExportCategoryTableMap::POSITION)
            ->findOne()
        ;

        $count = CategoryQuery::create()->count();

        if ($count > $max) {
            $max = $count;
        }

        $position = $this->getPosition();

        if ($position < $max) {

            $next = ExportCategoryQuery::create()
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
        $reverse = ExportCategoryQuery::create()
            ->findOneByPosition($position)
        ;

        if (null !== $reverse) {
            $reverse->setPosition($this->getPosition())->save();
        }

        $this->setPosition($position)->save();
    }
}