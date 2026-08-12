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
use Thelia\Domain\DataTransfer\Import\AbstractImport;
use Thelia\Model\Base\Import as BaseImport;
use Thelia\Model\Tools\PositionManagementTrait;

class Import extends BaseImport
{
    use PositionManagementTrait;

    /**
     * Whether the class named by handle_class can still be loaded. It cannot when the
     * module that declared the import has been removed, as nothing deletes the import
     * entries a module leaves behind.
     */
    public function isHandlerAvailable(): bool
    {
        $class = ltrim((string) $this->getHandleClass(), '\\');

        return '' !== $class && class_exists($class) && is_subclass_of($class, AbstractImport::class);
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function addCriteriaToPositionQuery($query): void
    {
        $query->filterByImportCategoryId($this->getImportCategoryId());
    }
}
