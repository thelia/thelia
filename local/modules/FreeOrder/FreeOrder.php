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

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
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
        $event = new OrderEvent($order);
        $event->setStatus(OrderStatusQuery::getPaidStatus()->getId());
        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
    }

    public function manageStockOnCreation()
    {
        return false;
    }
}
