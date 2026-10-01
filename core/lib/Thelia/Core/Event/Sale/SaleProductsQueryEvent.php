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

namespace Thelia\Core\Event\Sale;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Sale;
use Thelia\Model\SaleProductQuery;

/**
 * Raised by Thelia\Action\Sale::updateProductsSaleStatus() once the selection of a sale is built and before it is read.
 *
 * A listener narrows the query in place to keep products out of the sale (a product status, a supplier, a flag of its
 * own...), so the exclusion is a SQL condition and not a loop over the whole selection. The query is already filtered
 * by the sale; a listener adds conditions and never replaces it.
 */
class SaleProductsQueryEvent extends ActionEvent
{
    public function __construct(
        private readonly Sale $sale,
        private readonly SaleProductQuery $query,
    ) {
    }

    public function getSale(): Sale
    {
        return $this->sale;
    }

    public function getQuery(): SaleProductQuery
    {
        return $this->query;
    }
}
