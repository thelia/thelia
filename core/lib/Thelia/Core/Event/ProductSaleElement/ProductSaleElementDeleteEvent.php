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
    protected $product_sale_element_id;
    protected $currency_id;

    public function __construct($product_sale_element_id, $currency_id)
    {
        parent::__construct();

        $this->product_sale_element_id = $product_sale_element_id;
        $this->setCurrencyId($currency_id);
    }

    public function getProductSaleElementId()
    {
        return $this->product_sale_element_id;
    }

    public function setProductSaleElementId($product_sale_element_id)
    {
        $this->product_sale_element_id = $product_sale_element_id;

        return $this;
    }

    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    public function setCurrencyId($currency_id)
    {
        $this->currency_id = $currency_id;

        return $this;
    }
}
