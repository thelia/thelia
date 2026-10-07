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

use Thelia\Domain\DataTransfer\Job\DataTransferJob;
use Thelia\Model\Base\ExportJob as BaseExportJob;
use Thelia\Model\Map\ExportJobTableMap;
use Thelia\Model\Tools\DataTransferJobTrait;

class ExportJob extends BaseExportJob implements DataTransferJob
{
    use DataTransferJobTrait;

    public function tableName(): string
    {
        return ExportJobTableMap::TABLE_NAME;
    }
}
