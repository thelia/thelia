<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Action\BaseAction;

class Cart extends BaseAction
{
    /**
     * 
     * add an article to cart
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function addCart(Request $request)
    {
        var_dump($this->getDispatcher()); exit;
    }
    
    /**
     * 
     * Delete specify article present into cart
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function deleteArticle(Request $request)
    {
        
    }
    
    /**
     * 
     * Modify article's quantity
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function modifyArticle(Request $request)
    {
        
    }
}

