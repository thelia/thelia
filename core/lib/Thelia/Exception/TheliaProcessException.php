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

/**
 * these exception are non fatal exception, due to thelia process exception
 * or customer random navigation
 *
 * they redirect the customer who trig them to a specific error page // @todo
 *
 * Class TheliaProcessException
 * @package Thelia\Exception
 */
class TheliaProcessException extends \RuntimeException
{
    public $data = null;

    const UNKNOWN_EXCEPTION = 0;

    const CART_ITEM_NOT_ENOUGH_STOCK = 100;
    const NO_PLACED_ORDER = 101;
    const PLACED_ORDER_ID_BAD_CURRENT_CUSTOMER = 102;

    public function __construct($message, $code = null, $data = null, $previous = null)
    {
        $this->data = $data;

        if ($code === null) {
            $code = self::UNKNOWN_EXCEPTION;
        }
        parent::__construct($message, $code, $previous);
    }
}
