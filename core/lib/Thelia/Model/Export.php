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
use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Model\Base\Export as BaseExport;
use Thelia\Model\Tools\PositionManagementTrait;

class Export extends BaseExport
{
    use PositionManagementTrait;

    protected ?AbstractExport $cache = null;

    /**
     * Whether the class named by handle_class can still be loaded. It cannot when the
     * module that declared the export has been removed, as nothing deletes the export
     * entries a module leaves behind.
     */
    public function isHandlerAvailable(): bool
    {
        $class = ltrim((string) $this->getHandleClass(), '\\');

        return '' !== $class && class_exists($class) && is_subclass_of($class, AbstractExport::class);
    }

    /**
     * @throws \ErrorException when the handler class is missing or is not an export
     */
    public function getHandleClassInstance(): AbstractExport
    {
        $class = '\\'.ltrim((string) $this->getHandleClass(), '\\');

        if ('\\' === $class || !class_exists($class)) {
            throw new \ErrorException(Translator::getInstance()->trans('The class "%class" doesn\'t exist', ['%class' => $class]));
        }

        $instance = new $class();

        if (!$instance instanceof AbstractExport) {
            throw new \ErrorException(Translator::getInstance()->trans('The class "%class" must extend %baseClass', ['%class' => $class, '%baseClass' => AbstractExport::class]));
        }

        return $instance;
    }

    public function hasImages()
    {
        return $this->handler()?->hasImages() ?? false;
    }

    public function hasDocuments()
    {
        return $this->handler()?->hasDocuments() ?? false;
    }

    public function useRangeDate()
    {
        return $this->handler()?->useRangeDate() ?? false;
    }

    private function handler(): ?AbstractExport
    {
        if (!$this->isHandlerAvailable()) {
            return null;
        }

        return $this->cache ??= $this->getHandleClassInstance();
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
