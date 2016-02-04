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

namespace Thelia\Core\Event\Cart;

use Thelia\Model\Cart;

class CartDuplicationEvent extends CartEvent
{
    protected $oldCart;

    public function __construct(Cart $newCart, Cart $oldCart)
    {
        parent::__construct($newCart);

        $this->oldCart = $oldCart;
    }

    /**
     * @return Cart
     */
    public function getNewCart()
    {
        return $this->cart;
    }

    /**
     * @return Cart
     */
    public function getOldCart()
    {
        return $this->oldCart;
    }
}
