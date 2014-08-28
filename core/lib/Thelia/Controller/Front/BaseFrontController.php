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

use Symfony\Component\Routing\Router;
use Thelia\Controller\BaseController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelper;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Tools\URL;

class BaseFrontController extends BaseController
{
    protected $currentRouter = "router.front";


    public function checkAuth()
    {
        if ($this->getSecurityContext()->hasCustomerUser() === false) {
            $this->redirectToRoute('customer.login.process');
        }
    }

    protected function checkCartNotEmpty()
    {
        $cart = $this->getSession()->getCart();
        if ($cart===null || $cart->countCartItems() == 0) {
            $this->redirectToRoute('cart.view');
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
            $this->redirectToRoute("order.delivery");
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
            $this->redirectToRoute("order.invoice");
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
        $parser->setTemplateDefinition($template ?: TemplateHelper::getInstance()->getActiveFrontTemplate());

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

        $session = $this->getSession();

        // Prepare common template variables
        $args = array_merge($args, array(
                'locale'               => $session->getLang()->getLocale(),
                'lang_code'            => $session->getLang()->getCode(),
                'lang_id'              => $session->getLang()->getId(),
                'current_url'          => $this->getRequest()->getUri()
            ));

        // Render the template.

        $data = $this->getParser($templateDir)->render($templateName, $args);

        return $data;

    }
}
