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

namespace Thelia\Controller\Front;

use Thelia\Controller\BaseController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;

class BaseFrontController extends BaseController
{
    const CONTROLLER_TYPE = 'front';

    protected $currentRouter = "router.front";

    public function checkAuth()
    {
        if ($this->getSecurityContext()->hasCustomerUser() === false) {
            throw new RedirectException($this->retrieveUrlFromRouteId('customer.login.process'));
        }
    }

    /**
     * @return string
     */
    public function getControllerType()
    {
        return self::CONTROLLER_TYPE;
    }

    protected function checkCartNotEmpty()
    {
        $cart = $this->getSession()->getSessionCart($this->getDispatcher());
        if ($cart===null || $cart->countCartItems() == 0) {
            throw new RedirectException($this->retrieveUrlFromRouteId('cart.view'));
        }
    }

    protected function checkValidDelivery()
    {
        $order = $this->getSession()->getOrder();
        if (null === $order
            ||
            null === $order->getChoosenDeliveryAddress()
            ||
            null === $order->getDeliveryModuleId()
            ||
            null === AddressQuery::create()->findPk($order->getChoosenDeliveryAddress())
            ||
            null === ModuleQuery::create()->findPk($order->getDeliveryModuleId())) {
            throw new RedirectException($this->retrieveUrlFromRouteId('order.delivery'));
        }
    }

    protected function checkValidInvoice()
    {
        $order = $this->getSession()->getOrder();
        if (null === $order
            ||
            null === $order->getChoosenInvoiceAddress()
            ||
            null === $order->getPaymentModuleId()
            ||
            null === AddressQuery::create()->findPk($order->getChoosenInvoiceAddress())
            ||
            null === ModuleQuery::create()->findPk($order->getPaymentModuleId())) {
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
        $parser = $this->container->get("thelia.parser");

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
     * @param  string                               $templateName the complete template name, with extension
     * @param  array                                $args         the template arguments
     * @param  int                                  $status       http code status
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function render($templateName, $args = array(), $status = 200)
    {
        return Response::create($this->renderRaw($templateName, $args), $status);
    }

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param string $templateName the complete template name, with extension
     * @param array  $args         the template arguments
     * @param string$templateDir
     *
     * @return string
     */
    protected function renderRaw($templateName, $args = array(), $templateDir = null)
    {
        // Add the template standard extension
        $templateName .= '.html';

        // Render the template.
        $data = $this->getParser($templateDir)->render($templateName, $args);

        return $data;
    }
}
