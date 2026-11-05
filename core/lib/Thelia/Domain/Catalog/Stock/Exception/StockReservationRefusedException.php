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
 * A step of the checkout could not hold a line of the cart: what is left of the stock, once
 * the other carts have taken what they hold, no longer covers it.
 *
 * A shortage to every caller that answers one (a 409 on the front API, the cart step of a
 * theme), with a sentence of its own: the product was in stock when it went in the cart.
 */
final class StockReservationRefusedException extends StockShortageException
{
    public function __construct(?string $productReference = null, ?\Throwable $previous = null)
    {
        parent::__construct(
            $productReference,
            $previous,
            Translator::getInstance()->trans('Product %ref is no longer available', ['%ref' => $productReference ?? '']),
        );
    }
}
