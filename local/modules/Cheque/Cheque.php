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

namespace Cheque;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Install\Database;
use Thelia\Model\MessageQuery;
use Thelia\Model\Order;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;

class Cheque extends BaseModule implements PaymentModuleInterface
{
    const MESSAGE_DOMAIN = "Cheque";

    public function pay(Order $order)
    {
        // Nothing special to to.
    }

    /**
     *
     * This method is call on Payment loop.
     *
     * If you return true, the payment method will de display
     * If you return false, the payment method will not be display
     *
     * @return boolean
     */
    public function isValidPayment()
    {
        return $this->getCurrentOrderTotalAmount() > 0;
    }

    public function postActivation(ConnectionInterface $con = null)
    {
        $database = new Database($con);

        // Insert email message
        $database->insertSql(null, array(__DIR__ . "/Config/setup.sql"));
    }

    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        // Delete our message
        if (null !== $message = MessageQuery::create()->findOneByName('order_confirmation_cheque')) {
            $message->delete($con);
        }

        parent::destroy($con, $deleteModuleData);
    }

    /**
     * if you want, you can manage stock in your module instead of order process.
     * Return false if you want to manage yourself the stock
     *
     * @return bool
     */
    public function manageStockOnCreation()
    {
        return false;
    }
}
