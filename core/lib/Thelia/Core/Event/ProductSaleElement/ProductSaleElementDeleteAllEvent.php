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

class ProductSaleElementDeleteAllEvent extends ProductSaleElementEvent
{
    protected $product_id;
    protected $currency_id;

    /**
     * @param int $product_id
     * @param int $currency_id
     */
    public function __construct($product_id, $currency_id)
    {
        parent::__construct();

        $this->product_id = $product_id;
        $this->currency_id = $currency_id;
    }

    /**
     * @return int
     */
    public function getProductId()
    {
        return $this->product_id;
    }

    /**
     * @return int
     */
    public function getCurrencyId()
    {
        return $this->currency_id;
    }
}
