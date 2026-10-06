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

use Thelia\Model\Base\ExportJobQuery as BaseExportJobQuery;
use Thelia\Model\Tools\ExpiredDataTransferJobFilterTrait;

class ExportJobQuery extends BaseExportJobQuery
{
    use ExpiredDataTransferJobFilterTrait;
}
