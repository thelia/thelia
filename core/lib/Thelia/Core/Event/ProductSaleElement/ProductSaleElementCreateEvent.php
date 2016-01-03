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

use Thelia\Model\Product;

class ProductSaleElementCreateEvent extends ProductSaleElementEvent
{
    protected $product;
    protected $attribute_av_list;
    protected $currency_id;

    public function __construct(Product $product, $attribute_av_list, $currency_id)
    {
        parent::__construct();

        $this->setAttributeAvList($attribute_av_list);
        $this->setCurrencyId($currency_id);
        $this->setProduct($product);
    }

    public function getAttributeAvList()
    {
        return $this->attribute_av_list;
    }

    public function setAttributeAvList($attribute_av_list)
    {
        $this->attribute_av_list = $attribute_av_list;

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

    /**
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
    }

    public function setProduct($product)
    {
        $this->product = $product;

        return $this;
    }
}
