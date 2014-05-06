<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Exception;

class OrderException extends \RuntimeException
{
    /**
     * @var string The cart template name
     */
    public $cartRoute = "cart.view";
    public $orderDeliveryRoute = "order.delivery";

    public $arguments = array();

    const UNKNOWN_EXCEPTION = 0;

    const CART_EMPTY = 100;

    const UNDEFINED_DELIVERY = 200;
    const DELIVERY_MODULE_UNAVAILABLE = 201;

    public function __construct($message, $code = null, $arguments = array(), $previous = null)
    {
        if (is_array($arguments)) {
            $this->arguments = $arguments;
        }
        if ($code === null) {
            $code = self::UNKNOWN_EXCEPTION;
        }
        parent::__construct($message, $code, $previous);
    }
}
