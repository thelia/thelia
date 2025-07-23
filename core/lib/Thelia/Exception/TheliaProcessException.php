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

namespace Thelia\Exception;

/**
 * these exception are non fatal exception, due to thelia process exception
 * or customer random navigation.
 *
 * they redirect the customer who trig them to a specific error page // @todo
 *
 * Class TheliaProcessException
 */
class TheliaProcessException extends \RuntimeException
{
    public const UNKNOWN_EXCEPTION = 0;
    public const CART_ITEM_NOT_ENOUGH_STOCK = 100;
    public const NO_PLACED_ORDER = 101;
    public const PLACED_ORDER_ID_BAD_CURRENT_CUSTOMER = 102;

    public function __construct($message, $code = null, public $data = null, $previous = null)
    {
        if (null === $code) {
            $code = self::UNKNOWN_EXCEPTION;
        }

        parent::__construct($message, $code, $previous);
    }
}
