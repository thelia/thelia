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

namespace Thelia\Domain\DataTransfer\Import;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\ImportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Domain\DataTransfer\Import\Type\ProductStockImport;

/**
 * Tells the listeners of the stock levels what a stock import changed, once, at the end of the
 * import: a sale element written by several rows is one change, from the stock before the first to
 * the stock after the last. The import runs in a transaction (the job that carries it), so the
 * announcer holds the changes until it is over, and tells none of a rolled back import.
 */
final readonly class ImportedStockAnnouncer
{
    public function __construct(private StockLevelAnnouncer $stockLevelAnnouncer)
    {
    }

    #[AsEventListener(event: TheliaEvents::IMPORT_FINISHED)]
    public function onImportFinished(ImportEvent $event): void
    {
        $import = $event->getImport();

        if ($import instanceof ProductStockImport) {
            $this->stockLevelAnnouncer->announceChanges($import->stockChanges());
        }
    }
}
