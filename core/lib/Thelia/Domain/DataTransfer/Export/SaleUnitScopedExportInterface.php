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

namespace Thelia\Domain\DataTransfer\Export;

/**
 * An export that can be narrowed to the orders placed on some sale units: the ones of the
 * administrator who asked for it. ExportHandler::export() hands it the units before the
 * file is written.
 */
interface SaleUnitScopedExportInterface
{
    /**
     * @param list<int>|null $saleUnitIds the sale units the export is restricted to (an order placed on none, a national
     *                                    order, is then left out; an empty list reaches no order), or null for no restriction
     */
    public function restrictToSaleUnits(?array $saleUnitIds): void;
}
