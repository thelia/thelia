<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
namespace Thelia\Controller\Front;

use Thelia\Core\Event\CartEvent;
use Thelia\Core\Event\TheliaEvents;

class CartController extends BaseFrontController
{
    use \Thelia\Cart\CartTrait;

    public function addArticle()
    {
        $cartEvent = $this->getCartEvent();

        $this->dispatch(TheliaEvents::CART_ADDITEM, $cartEvent);

        $this->redirectInternal();
    }

    public function modifyArticle()
    {
        $cartEvent = $this->getCartEvent();

        $this->dispatch(TheliaEvents::CART_CHANGEITEM, $cartEvent);

        $this->redirectInternal();
    }

    public function deleteArticle()
    {
        $cartEvent = $this->getCartEvent();

        $this->dispatch(TheliaEvents::CART_DELETEITEM, $cartEvent);

        $this->redirectInternal();
    }

    protected function getCartEvent()
    {
        $request = $this->getRequest();
        $cart = $this->getCart($request);

        return new CartEvent($request, $cart);
    }

}
