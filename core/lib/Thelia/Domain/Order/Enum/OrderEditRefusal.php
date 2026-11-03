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

namespace Thelia\Domain\Order\Enum;

enum OrderEditRefusal: string
{
    case StatusNotEditable = 'status_not_editable';
    case InvoiceIssued = 'invoice_issued';
    case NothingToEdit = 'nothing_to_edit';
    case LastLine = 'last_line';
    case UnknownLine = 'unknown_line';
    case ServiceLine = 'service_line';
    case LineInUse = 'line_in_use';
    case UnknownProduct = 'unknown_product';
    case InvalidQuantity = 'invalid_quantity';
    case InvalidPrice = 'invalid_price';
    case InvalidDiscount = 'invalid_discount';
    case InvalidPostage = 'invalid_postage';
    case PostageTaxUnknown = 'postage_tax_unknown';
    case StaleState = 'stale_state';
}
