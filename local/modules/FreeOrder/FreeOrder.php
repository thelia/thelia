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

namespace FreeOrder;

use Thelia\Model\Order;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;

class FreeOrder extends BaseModule implements PaymentModuleInterface
{
    public function isValidPayment()
    {
        return $this->getCurrentOrderTotalAmount() == 0;
    }

    public function pay(Order $order)
    {
        // We have nothing to do here.
    }

    public function manageStockOnCreation()
    {
        return false;
    }
}
