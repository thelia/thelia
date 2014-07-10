<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\Export as BaseExport;
use Thelia\Model\Map\ExportTableMap;

class Export extends BaseExport
{
    public function upPosition()
    {
        if (($position = $this->getPosition()) > 1) {

            $previous = ExportQuery::create()
                ->filterByPosition($position - 1)
                ->findOneByExportCategoryId($this->getExportCategoryId());

            if (null !== $previous) {
                $previous->setPosition($position)->save();
            }

            $this->setPosition($position - 1)->save();
        }

        return $this;
    }

    public function downPosition()
    {
        $max = ExportQuery::create()
            ->orderByPosition(Criteria::DESC)
            ->select(ExportTableMap::POSITION)
            ->findOne()
        ;

        $count = $this->getExportCategory()->countExports();

        if ($count > $max) {
            $max = $count;
        }

        $position = $this->getPosition();

        if ($position < $max) {

            $next = ExportQuery::create()
                ->filterByPosition($position + 1)
                ->findOneByExportCategoryId($this->getExportCategoryId());

            if (null !== $next) {
                $next->setPosition($position)->save();
            }

            $this->setPosition($position + 1)->save();
        }

        return $this;
    }

    public function updatePosition($position)
    {
        $reverse = ExportQuery::create()
            ->findOneByPosition($position)
        ;

        if (null !== $reverse) {
            $reverse->setPosition($this->getPosition())->save();
        }

        $this->setPosition($position)->save();
    }
}
