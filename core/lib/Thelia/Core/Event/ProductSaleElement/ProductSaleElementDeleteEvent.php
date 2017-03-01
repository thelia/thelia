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

class ProductSaleElementDeleteEvent extends ProductSaleElementEvent
{
    /** @var int */
    protected $product_sale_element_id;
    /** @var  int */
    protected $currency_id;
    
    /**
     * ProductSaleElementDeleteEvent constructor.
     * @param int $product_sale_element_id
     * @param int $currency_id
     */
    public function __construct($product_sale_element_id, $currency_id)
    {
        parent::__construct();

        $this->product_sale_element_id = $product_sale_element_id;
        $this->currency_id = $currency_id;
    }
    
    /**
     * @return int
     */
    public function getProductSaleElementId()
    {
        return $this->product_sale_element_id;
    }
    
    /**
     * @param int $product_sale_element_id
     * @return $this
     */
    public function setProductSaleElementId($product_sale_element_id)
    {
        $this->product_sale_element_id = $product_sale_element_id;

        return $this;
    }
    
    /**
     * @return int
     */
    public function getCurrencyId()
    {
        return $this->currency_id;
    }
    
    /**
     * @param int $currency_id
     * @return $this
     */
    public function setCurrencyId($currency_id)
    {
        $this->currency_id = $currency_id;

        return $this;
    }
}
