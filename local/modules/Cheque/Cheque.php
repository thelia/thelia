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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Install\Database;
use Thelia\Model\ModuleImageQuery;
use Thelia\Model\Order;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;
use Thelia\Tools\URL;

class Cheque extends BaseModule implements PaymentModuleInterface
{
    const MESSAGE_DOMAIN = "Cheque";

    public function pay(Order $order)
    {
        // no special process, waiting for the cheque.
        $router = $this->getContainer()->get('router.cheque');

        $thankYouPageUrl = URL::getInstance()->absoluteUrl(
            $router->generate('cheque.order-placed', ['orderId' => $order->getId()])
        );

        $orderEvent = new OrderEvent($order);

        // Clear the cart
        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_CART_CLEAR, $orderEvent);

        // Redirect to our own route, so that our payment page is displayed.
        return RedirectResponse::create($thankYouPageUrl);
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
        return true;
    }

    public function postActivation(ConnectionInterface $con = null)
    {
        /* insert the images from image folder if first module activation */
        $moduleModel = $this->getModuleModel();

        if (! $moduleModel->isModuleImageDeployed($con)) {
            $this->deployImageFolder($moduleModel, sprintf('%s/images', __DIR__), $con);
        }

        $database = new Database($con->getWrappedConnection());

        // Insert email message
        $database->insertSql(null, array(__DIR__ . "/Config/cheque.sql"));
    }

    public function getCode()
    {
        return 'Cheque';
    }
}
