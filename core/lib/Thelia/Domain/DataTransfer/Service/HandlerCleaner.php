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

namespace Thelia\Domain\DataTransfer\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Domain\DataTransfer\Import\AbstractImport;
use Thelia\Model\Export;
use Thelia\Model\ExportCategory;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Import;
use Thelia\Model\ImportCategory;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;

/**
 * Removes the export and import entries a module left behind.
 *
 * The export and import tables hold no reference to the module that declared a
 * handler, so the owner is read back from the handler class: a module declares
 * handlers that live under its own root namespace. Entries a module no longer
 * accounts for are deleted, along with the categories they leave empty.
 */
class HandlerCleaner
{
    /**
     * Deletes the entries whose handler class belongs to the given module namespace,
     * for instance "Acme\Acme" for a module whose handlers live under "Acme\".
     */
    public function removeHandlersProvidedBy(string $moduleNamespace, ?ConnectionInterface $con = null): int
    {
        $rootNamespace = $this->rootNamespace($moduleNamespace);

        if ('' === $rootNamespace) {
            return 0;
        }

        $exports = array_filter(
            iterator_to_array(ExportQuery::create()->find($con)),
            fn (Export $export): bool => $rootNamespace === $this->rootNamespace((string) $export->getHandleClass()),
        );

        $imports = array_filter(
            iterator_to_array(ImportQuery::create()->find($con)),
            fn (Import $import): bool => $rootNamespace === $this->rootNamespace((string) $import->getHandleClass()),
        );

        return $this->remove($exports, $imports, $con);
    }

    /**
     * Deletes the entries whose handler class can no longer be loaded, which is
     * what a module removed before its entries were cleaned up leaves behind.
     */
    public function removeUnavailableHandlers(?ConnectionInterface $con = null): int
    {
        [$exports, $imports] = $this->findUnavailableHandlers($con);

        return $this->remove($exports, $imports, $con);
    }

    /**
     * @return array{0: list<Export>, 1: list<Import>}
     */
    public function findUnavailableHandlers(?ConnectionInterface $con = null): array
    {
        $exports = [];
        foreach (ExportQuery::create()->find($con) as $export) {
            if (!$this->isAvailable((string) $export->getHandleClass(), AbstractExport::class)) {
                $exports[] = $export;
            }
        }

        $imports = [];
        foreach (ImportQuery::create()->find($con) as $import) {
            if (!$this->isAvailable((string) $import->getHandleClass(), AbstractImport::class)) {
                $imports[] = $import;
            }
        }

        return [$exports, $imports];
    }

    /**
     * @param iterable<Export> $exports
     * @param iterable<Import> $imports
     */
    private function remove(iterable $exports, iterable $imports, ?ConnectionInterface $con): int
    {
        $removed = 0;
        $exportCategories = [];
        $importCategories = [];

        foreach ($exports as $export) {
            $exportCategories[$export->getExportCategoryId()] = true;
            $export->delete($con);
            ++$removed;
        }

        foreach ($imports as $import) {
            $importCategories[$import->getImportCategoryId()] = true;
            $import->delete($con);
            ++$removed;
        }

        foreach (array_keys($exportCategories) as $categoryId) {
            $category = ExportCategoryQuery::create()->findPk($categoryId, $con);
            $remaining = ExportQuery::create()->filterByExportCategoryId($categoryId)->count($con);

            if ($category instanceof ExportCategory && 0 === $remaining) {
                $category->delete($con);
            }
        }

        foreach (array_keys($importCategories) as $categoryId) {
            $category = ImportCategoryQuery::create()->findPk($categoryId, $con);
            $remaining = ImportQuery::create()->filterByImportCategoryId($categoryId)->count($con);

            if ($category instanceof ImportCategory && 0 === $remaining) {
                $category->delete($con);
            }
        }

        return $removed;
    }

    /**
     * @param class-string $expectedBaseClass
     */
    private function isAvailable(string $handleClass, string $expectedBaseClass): bool
    {
        $handleClass = ltrim($handleClass, '\\');

        return '' !== $handleClass
            && class_exists($handleClass)
            && is_subclass_of($handleClass, $expectedBaseClass);
    }

    private function rootNamespace(string $class): string
    {
        $class = ltrim($class, '\\');
        $separatorPosition = strpos($class, '\\');

        return false === $separatorPosition ? $class : substr($class, 0, $separatorPosition);
    }
}
