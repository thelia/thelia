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

use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
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
        /** @var ParserResolver $parserResolver */
        $parserResolver = $this->getContainer()->get('thelia.parser.resolver');

        /** @var TemplateHelperInterface $templateHelper */
        $templateHelper = $this->getContainer()->get('thelia.template_helper');

        $parser = $parserResolver->getParser(
            $templateHelper->getActiveFrontTemplate()->getAbsolutePath(),
            null
        );

        $parser->setTemplateDefinition($templateHelper->getActiveFrontTemplate());

        $renderedTemplate = $parser->render(
            'checkout-gateway', [
                'order_id' => $order->getId(),
                'cart_count' => $this->cartItemCount(),
                'gateway_url' => $gateway_url,
                'payment_form_data' => $form_data,
            ]
        );

        return new Response($renderedTemplate);
    }

    /**
     * How many lines the cart of the session holds, and none when there is no session to
     * ask.
     *
     * The gateway form is rendered from the payment module, which is called at the very
     * end of the placement — so this used to reach into the session of a request that,
     * from the front API or a command line, either is not there or carries no session at
     * all, and `Request::getSession()` throws on the latter. Throwing there aborts a
     * payment for an order that has already been written, over a number no template the
     * core ships even reads.
     *
     * Zero on purpose rather than a count taken off the order: the count is what the
     * session holds, and a caller with no session holds nothing. A module that wants the
     * lines of the order has the order.
     */
    protected function cartItemCount(): int
    {
        $request = $this->currentRequest();
        $session = $request?->hasSession() === true ? $request->getSession() : null;

        if (!$session instanceof Session) {
            return 0;
        }

        return $session->getSessionCart($this->getDispatcher())->getCartItems()->count();
    }

    /**
     * The request behind this call, when there is one. `getRequest()` throws instead of
     * answering null, which is the right thing for a module that cannot work without one
     * and the wrong thing here.
     */
    private function currentRequest(): ?HttpFoundationRequest
    {
        if ($this->hasRequest()) {
            return $this->getRequest();
        }

        if (!$this->hasContainer()) {
            return null;
        }

        return $this->getContainer()->get('request_stack')?->getMainRequest();
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
        /** @var Router $frontOfficeRouter */
        $frontOfficeRouter = $this->container->get('router');

        return URL::getInstance()->absoluteUrl(
            $frontOfficeRouter?->generate(
                'checkout_confirm',
                [
                    'order_id' => $order_id,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL,
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
        /** @var Router $frontOfficeRouter */
        $frontOfficeRouter = $this->container->get('router');

        return URL::getInstance()->absoluteUrl(
            $frontOfficeRouter?->generate(
                'checkout_failed',
                [
                    'order_id' => $order_id,
                    'message' => $message,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL,
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

    /**
     * Whether an order this module was already asked to pay may be presented to it
     * again, on a new payment attempt, instead of being cancelled and placed anew.
     *
     * False unless the module says otherwise. A module may only say so when its
     * provider reference varies at each attempt and its notification finds the order
     * back by the order's own reference: a module that writes a single provider key on
     * the order and overwrites it at each attempt would let a late notification of the
     * first attempt land on the wrong transaction, or on none.
     */
    public function supportsPaymentRetry(): bool
    {
        return false;
    }

    public function getMinimumAmount(): int
    {
        return 0;
    }

    public function getMaximumAmount(): int
    {
        return 10000000;
    }
}
