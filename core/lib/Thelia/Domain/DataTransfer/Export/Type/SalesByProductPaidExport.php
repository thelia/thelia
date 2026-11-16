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

namespace Thelia\Domain\DataTransfer\Export\Type;

use Thelia\Domain\Report\Sales\StatusScope;

/**
 * The sales by product of the paid orders only: the file of the back-office screen when it
 * counts the paid orders.
 */
final class SalesByProductPaidExport extends SalesByProductExport
{
    public const FILE_NAME = 'sales_by_product_paid';

    protected function scope(): StatusScope
    {
        return StatusScope::Paid;
    }
}
