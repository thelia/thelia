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

class ProductDeleteAccessoryEvent extends ProductEvent
{
    protected $accessory_id;

    public function __construct(Product $product, $accessory_id)
    {
        parent::__construct($product);

        $this->accessory_id = $accessory_id;
    }

    public function getAccessoryId()
    {
        return $this->accessory_id;
    }

    public function setAccessoryId($accessory_id)
    {
        $this->accessory_id = $accessory_id;
    }
}
