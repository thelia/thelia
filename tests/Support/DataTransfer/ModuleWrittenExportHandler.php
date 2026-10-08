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
namespace Thelia\Tests\Support\DataTransfer;

use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Core\Serializer\SerializerInterface;

/**
 * An export handler a module extended to write the file its own way, as the
 * protected processExport() lets it.
 */
final class ModuleWrittenExportHandler extends ExportHandler
{
    public bool $writesItsOwnWay = false;

    protected function processExport(AbstractExport $export, SerializerInterface $serializer): string
    {
        if (!$this->writesItsOwnWay) {
            return parent::processExport($export, $serializer);
        }

        $filePath = THELIA_CACHE_DIR.'export'.DS.uniqid('', true).'-'.$export->getFileName().'.'.$serializer->getExtension();
        file_put_contents($filePath, 'written by a module');

        return $filePath;
    }
}
