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

namespace VirtualProductDelivery;

use Thelia\Core\Translation\Translator;
use Thelia\Model\Country;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\Exception\DeliveryException;

class VirtualProductDelivery extends AbstractDeliveryModule
{
    /**
     * The module is valid if the cart contains only virtual products.
     *
     * @param Country $country
     *
     * @return bool true if there is only virtual products in cart elsewhere false
     */
    public function isValidDelivery(Country $country)
    {
        $cart = $this->getRequest()->getSession()->getCart();
        foreach ($cart->getCartItems() as $cartItem) {
            if (!$cartItem->getProduct()->getVirtual()) {
                return false;
            }
        }

        return true;
    }

    public function getPostage(Country $country)
    {
        if (!$this->isValidDelivery($country)) {
            throw new DeliveryException(
                Translator::getInstance()->trans("This module cannot be used on the current cart.")
            );
        }

        return 0.0;
    }

}
