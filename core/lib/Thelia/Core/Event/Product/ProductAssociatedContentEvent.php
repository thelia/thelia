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

namespace Thelia\Core\Event\Product;

use Thelia\Model\ProductAssociatedContent;
use Thelia\Core\Event\ActionEvent;

class ProductAssociatedContentEvent extends ActionEvent
{
    public $content = null;

    public function __construct(ProductAssociatedContent $content = null)
    {
        $this->content = $content;
    }

    public function hasProductAssociatedContent()
    {
        return ! is_null($this->content);
    }

    public function getProductAssociatedContent()
    {
        return $this->content;
    }

    public function setProductAssociatedContent(ProductAssociatedContent $content)
    {
        $this->content = $content;

        return $this;
    }
}
