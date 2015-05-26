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

namespace Thelia\Core\Event\ProductSaleElement;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Product;

/**
 * Event fired when cloning a product's PSEs
 *
 * Class ProductSaleElementCloneEvent
 * @package Thelia\Core\Event\ProductSaleElement
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class ProductSaleElementCloneEvent extends ActionEvent
{
    protected $originalProduct = array();
    protected $clonedProduct = array();

    public function __construct(
        $originalProduct,
        $clonedProduct
    ) {
        $this->originalProduct = $originalProduct;
        $this->clonedProduct = $clonedProduct;
    }

    /**
     * @return Product
     */
    public function getOriginalProduct()
    {
        return $this->originalProduct;
    }

    /**
     * @param Product $originalProduct
     */
    public function setOriginalProduct($originalProduct)
    {
        $this->originalProduct = $originalProduct;
    }

    /**
     * @return Product
     */
    public function getClonedProduct()
    {
        return $this->clonedProduct;
    }

    /**
     * @param Product $clonedProduct
     */
    public function setClonedProduct($clonedProduct)
    {
        $this->clonedProduct = $clonedProduct;
    }


}