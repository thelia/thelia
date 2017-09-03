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
    protected $originalCart;

    public function __construct(Cart $duplicatedCart, Cart $originalCart)
    {
        parent::__construct($duplicatedCart);

        $this->originalCart = $originalCart;
    }

    /**
     * @return Cart
     */
    public function getDuplicatedCart()
    {
        return $this->getCart();
    }

    /**
     * @return Cart
     */
    public function getOriginalCart()
    {
        return $this->originalCart;
    }
}
