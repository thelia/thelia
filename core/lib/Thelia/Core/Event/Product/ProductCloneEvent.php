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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Product;

class ProductCloneEvent extends ActionEvent
{
    protected $ref;
    protected $lang;
    protected $originalProduct = array();
    protected $clonedProduct = array();
    protected $types = array('images', 'documents');

    public function __construct(
        $ref,
        $lang,
        $originalProduct
    ) {
        $this->ref = $ref;
        $this->lang = $lang;
        $this->originalProduct = $originalProduct;
    }

    /**
     * @return mixed
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @param mixed $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param mixed $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
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

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }
}
