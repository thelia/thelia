<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\ImportExport\ExportHandlerInterface;
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

    /**
     * @param ContainerInterface $container
     * @return ExportHandlerInterface
     * @throws \ErrorException
     */
    public function getHandleClassInstance(ContainerInterface $container)
    {
        $class = $this->getHandleClass();

        if (!class_exists($class)) {
            throw new \ErrorException(
                "The class \"%class\" doesn't exist",
                [
                    "%class" => $class
                ]
            );
        }

        $instance = new $class($container);

        if (!$class instanceof ExportHandlerInterface) {
            throw new \ErrorException(
                "The class \"%class\" must implement %interface",
                [
                    "%class" => $class,
                    "%interface" => "\\Thelia\\ImportExport\\ExportHandlerInterface",
                ]
            );
        }

        return $instance;
    }
}
