<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
