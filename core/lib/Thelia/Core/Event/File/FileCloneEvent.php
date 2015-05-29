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

namespace Thelia\Core\Event\File;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Product;

/**
 * Event fired when cloning a product's files
 *
 * Class FileCloneEvent
 * @package Thelia\Core\Event\File
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class FileCloneEvent extends ActionEvent
{
    protected $originalProductId;
    protected $clonedProduct = array();
    protected $types = array();

    public function __construct(
        $originalProductId,
        $clonedProduct,
        $types
    ) {
        $this->originalProductId = $originalProductId;
        $this->clonedProduct = $clonedProduct;
        $this->types = $types;
    }

    /**
     * @return mixed
     */
    public function getOriginalProductId()
    {
        return $this->originalProductId;
    }

    /**
     * @param mixed $originalProductId
     */
    public function setOriginalProductId($originalProductId)
    {
        $this->originalProductId = $originalProductId;
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

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param array $types
     */
    public function setTypes($types)
    {
        $this->types = $types;
    }

}
