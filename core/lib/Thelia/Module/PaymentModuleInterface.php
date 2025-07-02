<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Module;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Order;

interface PaymentModuleInterface extends BaseModuleInterface
{
    /**
     *  Method used by payment gateway.
     *
     *  If this method return a \Symfony\Component\HttpFoundation\Response instance, this response is send to the
     *  browser.
     *
     *  In many cases, it's necessary to send a form to the payment gateway. On your response you can return this form already
     *  completed, ready to be sent
     *
     * @param Order $order processed order
     *
     * @return Response|null
     */
    public function pay(Order $order);

    /**
     * This method is call on Payment loop.
     *
     * If you return true, the payment method will de display
     * If you return false, the payment method will not be display
     *
     * @return bool
     */
    public function isValidPayment();

    /**
     * if you want, you can manage stock in your module instead of order process.
     * Return false to decrease the stock when order status switch to pay.
     *
     * @return bool
     */
    public function manageStockOnCreation();
}
