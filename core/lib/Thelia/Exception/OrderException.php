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

class OrderException extends \RuntimeException
{
    /** @var string The cart template name */
    public string $cartRoute = 'cart.view';

    public $orderDeliveryRoute = 'order.delivery';
    public $arguments = [];

    public const UNKNOWN_EXCEPTION = 0;
    public const CART_EMPTY = 100;
    public const UNDEFINED_DELIVERY = 200;
    public const DELIVERY_MODULE_UNAVAILABLE = 201;

    public function __construct($message, $code = null, $arguments = [], $previous = null)
    {
        if (\is_array($arguments)) {
            $this->arguments = $arguments;
        }

        if (null === $code) {
            $code = self::UNKNOWN_EXCEPTION;
        }

        parent::__construct($message, $code, $previous);
    }
}
