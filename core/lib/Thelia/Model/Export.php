<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\Model\Base\Export as BaseExport;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class Export extends BaseExport
{
    use PositionManagementTrait;
    use ModelEventDispatcherTrait;

    /**
     * @var \Thelia\ImportExport\Export\AbstractExport
     */
    protected static $cache;

    /**
     * @throws \ErrorException
     *
     * @return \Thelia\ImportExport\Export\ExportHandler
     */
    public function getHandleClassInstance()
    {
        $class = $this->getHandleClass();

        if ($class[0] !== '\\') {
            $class = '\\' . $class;
        }

        if (!class_exists($class)) {
            $this->delete();

            throw new \ErrorException(
                Translator::getInstance()->trans(
                    'The class "%class" doesn\'t exist',
                    [
                        '%class' => $class
                    ]
                )
            );
        }

        $instance = new $class();

        if (!$instance instanceof AbstractExport) {
            $this->delete();

            throw new \ErrorException(
                Translator::getInstance()->trans(
                    'The class "%class" must extend %baseClass',
                    [
                        '%class' => $class,
                        '%baseClass' => 'Thelia\\ImportExport\\Export\\AbstractExport',
                    ]
                )
            );
        }

        return $instance;
    }

    public function hasImages()
    {
        if (static::$cache === null) {
            static::$cache = $this->getHandleClassInstance();
        }

        return static::$cache->hasImages();
    }

    public function hasDocuments()
    {
        if (static::$cache === null) {
            static::$cache = $this->getHandleClassInstance();
        }

        return static::$cache->hasDocuments();
    }

    public function useRangeDate()
    {
        if (static::$cache === null) {
            static::$cache = $this->getHandleClassInstance();
        }

        return static::$cache->useRangeDate();
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
