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
use Symfony\Component\Routing\Router;
use Thelia\Core\Template\ParserInterface;
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
     * @return Response the HTTP response
     */
    public function generateGatewayFormResponse(Order $order, string $gateway_url, array $form_data): Response
    {
        /** @var ParserInterface $parser */
        $parser = $this->getContainer()->get('thelia.parser');

        $parser->setTemplateDefinition(
            $parser->getTemplateHelper()->getActiveFrontTemplate(),
        );

        $renderedTemplate = $parser->render(
            'order-payment-gateway.html',
            [
                'order_id' => $order->getId(),
                'cart_count' => $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getCartItems()->count(),
                'gateway_url' => $gateway_url,
                'payment_form_data' => $form_data,
            ],
        );

        return new Response($renderedTemplate);
    }

    /**
     * Return the order payment success page URL.
     *
     * @param int $order_id the order ID
     *
     * @return string the order payment success page URL
     */
    public function getPaymentSuccessPageUrl(int $order_id): string
    {
        $frontOfficeRouter = $this->getContainer()->get('router.front');

        return URL::getInstance()->absoluteUrl(
            $frontOfficeRouter->generate(
                'order.placed',
                ['order_id' => $order_id],
                Router::ABSOLUTE_URL,
            ),
        );
    }

    /**
     * Redirect the customer to the failure payment page. if $message is null, a generic message is displayed.
     *
     * @param int         $order_id the order ID
     * @param string|null $message  an error message
     *
     * @return string the order payment failure page URL
     */
    public function getPaymentFailurePageUrl(int $order_id, ?string $message): string
    {
        $frontOfficeRouter = $this->getContainer()->get('router.front');

        return URL::getInstance()->absoluteUrl(
            $frontOfficeRouter->generate(
                'order.failed',
                [
                    'order_id' => $order_id,
                    'message' => $message,
                ],
                Router::ABSOLUTE_URL,
            ),
        );
    }

    /**
     * @inherited
     */
    public function manageStockOnCreation(): bool
    {
        return true;
    }

    public function getMinimumAmount(): void
    {
    }

    public function getMaximumAmount(): void
    {
    }
}
