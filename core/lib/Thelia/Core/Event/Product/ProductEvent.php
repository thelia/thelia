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

namespace Thelia\Core\Event\Product;

use Thelia\Model\Product;
use Thelia\Core\Event\ActionEvent;

class ProductEvent extends ActionEvent
{
    public $product = null;

    public function __construct(Product $product = null)
    {
        $this->product = $product;
    }

    public function hasProduct()
    {
        return ! is_null($this->product);
    }

    public function getProduct()
    {
        return $this->product;
    }

    public function setProduct(Product $product)
    {
        $this->product = $product;

        return $this;
    }
}
