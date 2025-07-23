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

namespace Thelia\Controller\Front;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\BaseController;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;

class BaseFrontController extends BaseController
{
    public const CONTROLLER_TYPE = 'front';

    protected string $currentRouter = 'router.front';

    public function checkAuth(): void
    {
        if (false === $this->getSecurityContext()->hasCustomerUser()) {
            throw new RedirectException($this->retrieveUrlFromRouteId('customer.login.process'));
        }
    }

    public function getControllerType(): string
    {
        return self::CONTROLLER_TYPE;
    }

    protected function checkCartNotEmpty(EventDispatcherInterface $eventDispatcher): void
    {
        $cart = $this->getSession()->getSessionCart($eventDispatcher);

        if (null === $cart || 0 === $cart->countCartItems()) {
            throw new RedirectException($this->retrieveUrlFromRouteId('cart.view'));
        }
    }

    protected function checkValidDelivery(): void
    {
        $order = $this->getSession()->getOrder();

        if (null === $order
            || null === $order->getChoosenDeliveryAddress()
            || null === $order->getDeliveryModuleId()
            || null === AddressQuery::create()->findPk($order->getChoosenDeliveryAddress())
            || null === ModuleQuery::create()->findPk($order->getDeliveryModuleId())) {
            throw new RedirectException($this->retrieveUrlFromRouteId('order.delivery'));
        }
    }

    protected function checkValidInvoice(): void
    {
        $order = $this->getSession()->getOrder();

        if (null === $order
            || null === $order->getChoosenInvoiceAddress()
            || null === $order->getPaymentModuleId()
            || null === AddressQuery::create()->findPk($order->getChoosenInvoiceAddress())
            || null === ModuleQuery::create()->findPk($order->getPaymentModuleId())) {
            throw new RedirectException($this->retrieveUrlFromRouteId('order.invoice'));
        }
    }

    protected function getParser(?string $template = null): ParserInterface
    {
        $path = $this->getTemplateHelper()->getActiveFrontTemplate()->getAbsolutePath();
        $parser = $this->parserResolver->getParser($path, $template);

        // Define the template that should be used
        $parser->setTemplateDefinition(
            $template ?: $this->getTemplateHelper()->getActiveFrontTemplate(),
            $this->useFallbackTemplate,
        );

        return $parser;
    }

    protected function render(string $templateName, array $args = [], int $status = 200): Response
    {
        return new Response($this->renderRaw($templateName, $args), $status);
    }

    protected function renderRaw(string $templateName, array $args = [], $templateDir = null): string
    {
        // Render the template.
        return $this->getParser()->render($templateName, $args);
    }
}
