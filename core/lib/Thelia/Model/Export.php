<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\DocumentsExportInterface;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\ImportExport\Export\ImagesExportInterface;
use Thelia\Model\Base\Export as BaseExport;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class Export extends BaseExport
{
    use PositionManagementTrait;
    use ModelEventDispatcherTrait;

    protected static $cache;

    /**
     * @param  ContainerInterface                        $container
     * @return \Thelia\ImportExport\Export\ExportHandler
     * @throws \ErrorException
     */
    public function getHandleClassInstance(ContainerInterface $container)
    {
        $class = $this->getHandleClass();

        if ($class[0] !== "\\") {
            $class = "\\" . $class;
        }

        if (!class_exists($class)) {
            $this->delete();

            throw new \ErrorException(
                Translator::getInstance()->trans(
                    "The class \"%class\" doesn't exist",
                    [
                        "%class" => $class
                    ]
                )
            );
        }

        $instance = new $class($container);

        if (!$instance instanceof ExportHandler) {
            $this->delete();

            throw new \ErrorException(
                Translator::getInstance()->trans(
                    "The class \"%class\" must extend %baseClass",
                    [
                        "%class" => $class,
                        "%baseClass" => "Thelia\\ImportExport\\Export\\ExportHandler",
                    ]
                )
            );
        }

        return static::$cache = $instance;
    }

    public function hasImages(ContainerInterface $container)
    {
        if (static::$cache === null) {
            $this->getHandleClassInstance($container);
        }

        return static::$cache instanceof ImagesExportInterface;
    }

    public function hasDocuments(ContainerInterface $container)
    {
        if (static::$cache === null) {
            $this->getHandleClassInstance($container);
        }

        return static::$cache instanceof DocumentsExportInterface;
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function addCriteriaToPositionQuery($query)
    {
        $query->filterByExportCategoryId($this->getExportCategoryId());
    }
}
