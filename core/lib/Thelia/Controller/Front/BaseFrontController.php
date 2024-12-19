<?php

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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Thelia\Controller\BaseController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;

class BaseFrontController extends BaseController
{
    public const CONTROLLER_TYPE = 'front';

    protected $currentRouter = 'router.front';

    #[Required]
    public ParserResolver $parserResolver;

    public function checkAuth(): void
    {
        if ($this->getSecurityContext()->hasCustomerUser() === false) {
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
        if ($cart === null || $cart->countCartItems() == 0) {
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

    /**
     * @param TemplateDefinition $template the template to process, or null for using the front template
     *
     * @return ParserInterface the Thelia parser²
     */
    protected function getParser($template = null)
    {
        $parser = $this->parserResolver->getParser($template);

        // Define the template that should be used
        $parser->setTemplateDefinition(
            $template ?: $this->getTemplateHelper()->getActiveFrontTemplate(),
            $this->useFallbackTemplate
        );

        return $parser;
    }

    /**
     * Render the given template, and returns the result as an Http Response.
     *
     * @param string $templateName the complete template name, with extension
     * @param array  $args         the template arguments
     * @param int    $status       http code status
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function render($templateName, $args = [], $status = 200)
    {
        return new Response($this->renderRaw($templateName, $args), $status);
    }

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param string $templateName the complete template name, with extension
     * @param array  $args         the template arguments
     * @param string $templateDir
     *
     * @return string
     */
    protected function renderRaw($templateName, $args = [], $templateDir = null)
    {
        // Render the template.
        return $this->getParser($templateName)->render($templateName, $args);
    }
}
