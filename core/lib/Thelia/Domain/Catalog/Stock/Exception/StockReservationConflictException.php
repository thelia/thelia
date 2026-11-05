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

namespace Thelia\Domain\Catalog\Stock\Exception;

use Thelia\Core\Translation\Translator;
use Thelia\Domain\Order\Exception\StockShortageException;

/**
 * The database broke a lock conflict between this reservation and another one, or an order,
 * taking the same stock at the same moment: a deadlock (1213), or on MariaDB 11.6 and later a
 * row changed since the transaction took its picture (1020). Nothing was written; asked again
 * a moment later, the cart is judged on what the other one left.
 */
final class StockReservationConflictException extends StockShortageException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            null,
            $previous,
            Translator::getInstance()->trans('Another cart took the same products at the same moment. Please try again.'),
        );
    }
}
