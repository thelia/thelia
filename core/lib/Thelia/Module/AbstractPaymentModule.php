<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Module;

use Symfony\Component\Routing\Router;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelper;
use Thelia\Model\Order;
use Thelia\Tools\URL;

abstract class AbstractPaymentModule extends BaseModule implements PaymentModuleInterface
{
    /**
     * Render the payment gateway template. The module should provide the gateway URL and the form fields names and values.
     *
     * @param Order  $order       the order
     * @param string $gateway_url the payment gateway URL
     * @param array  $form_data   an associative array of form data, that will be rendered as hiddent fields
     *
     * @return Response the HTTP response.
     */
    public function generateGatewayFormResponse($order, $gateway_url, $form_data)
    {
        /** @var ParserInterface $parser */
        $parser = $this->container->get("thelia.parser");

        $parser->setTemplateDefinition(TemplateHelper::getInstance()->getActiveFrontTemplate());

        $renderedTemplate = $parser->render(
            "order-payment-gateway.html",
            array(
                "order_id"          => $order->getId(),
                "cart_count"        => $this->getRequest()->getSession()->getCart()->getCartItems()->count(),
                "gateway_url"       => $gateway_url,
                "payment_form_data" => $form_data
            )
        );

        return Response::create($renderedTemplate);
    }

    /**
     * Return the order payment success page URL
     *
     * @param  int    $order_id the order ID
     * @return string the order payment success page URL
     */
    public function getPaymentSuccessPageUrl($order_id)
    {
        $frontOfficeRouter = $this->getContainer()->get('router.front');

        return URL::getInstance()->absoluteUrl(
            $frontOfficeRouter->generate(
                "order.placed",
                array("order_id" => $order_id),
                Router::ABSOLUTE_URL
            )
        );
    }

    /**
     * Redirect the customer to the failure payment page. if $message is null, a generic message is displayed.
     *
     * @param int         $order_id the order ID
     * @param string|null $message  an error message.
     *
     * @return string the order payment failure page URL
     */
    public function getPaymentFailurePageUrl($order_id, $message)
    {
        $frontOfficeRouter = $this->getContainer()->get('router.front');

        return URL::getInstance()->absoluteUrl(
            $frontOfficeRouter->generate(
                "order.failed",
                array(
                    "order_id" => $order_id,
                    "message" => $message
                ),
                Router::ABSOLUTE_URL
            )
        );
    }
}