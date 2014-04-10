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

namespace Thelia\Core\Event\Cart;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CartItem;

/**
 * Class CartItemDuplicationItem
 * @package Thelia\Core\Event\Cart
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CartItemDuplicationItem extends ActionEvent
{
    /**
     * @var \Thelia\Model\CartItem
     */
    protected $oldItem;

    /**
     * @var \Thelia\Model\CartItem
     */
    protected $newItem;

    public function __construct(CartItem $newItem, CartItem $oldItem)
    {
        $this->newItem = $newItem;
        $this->oldItem = $oldItem;
    }

    /**
     * @return \Thelia\Model\CartItem
     */
    public function getNewItem()
    {
        return $this->newItem;
    }

    /**
     * @return \Thelia\Model\CartItem
     */
    public function getOldItem()
    {
        return $this->oldItem;
    }

}
