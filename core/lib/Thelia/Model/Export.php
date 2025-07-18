<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Base\Export as BaseExport;
use Thelia\Model\Tools\PositionManagementTrait;

class Export extends BaseExport
{
    use PositionManagementTrait;

    protected static AbstractExport $cache;

    /**
     * @throws \ErrorException
     */
    public function getHandleClassInstance(): ExportHandler
    {
        $class = $this->getHandleClass();

        if ('\\' !== $class[0]) {
            $class = '\\'.$class;
        }

        if (!class_exists($class)) {
            $this->delete();

            throw new \ErrorException(Translator::getInstance()->trans('The class "%class" doesn\'t exist', ['%class' => $class]));
        }

        $instance = new $class();

        if (!$instance instanceof AbstractExport) {
            $this->delete();

            throw new \ErrorException(Translator::getInstance()->trans('The class "%class" must extend %baseClass', ['%class' => $class, '%baseClass' => AbstractExport::class]));
        }

        return $instance;
    }

    public function hasImages()
    {
        if (null === static::$cache) {
            static::$cache = $this->getHandleClassInstance();
        }

        return static::$cache->hasImages();
    }

    public function hasDocuments()
    {
        if (null === static::$cache) {
            static::$cache = $this->getHandleClassInstance();
        }

        return static::$cache->hasDocuments();
    }

    public function useRangeDate()
    {
        if (null === static::$cache) {
            static::$cache = $this->getHandleClassInstance();
        }

        return static::$cache->useRangeDate();
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function addCriteriaToPositionQuery($query): void
    {
        $query->filterByExportCategoryId($this->getExportCategoryId());
    }
}
