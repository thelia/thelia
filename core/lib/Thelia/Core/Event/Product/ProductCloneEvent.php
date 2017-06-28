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
    /** @var  string */
    protected $ref;
    /** @var  string */
    protected $lang;
    /** @var  Product */
    protected $originalProduct;
    /** @var  Product */
    protected $clonedProduct;
    /** @var array */
    protected $types = array('images', 'documents');

    /**
     * ProductCloneEvent constructor.
     * @param string $ref
     * @param string $lang the locale (such as fr_FR)
     * @param $originalProduct
     */
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
     * @return string
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @param string $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
    }

    /**
     * @return string the locale (such as fr_FR)
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param string $lang the locale (such as fr_FR)
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
