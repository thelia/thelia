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

namespace Thelia\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Router;

use Thelia\Core\Template\TemplateHelper;
use Thelia\Core\Translation\Translator;
use Thelia\Form\FirewallForm;
use Thelia\Model\OrderQuery;

use Thelia\Tools\Redirect;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Event\ActionEvent;

use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Symfony\Component\EventDispatcher\Event;
use Thelia\Core\Event\DefaultActionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Tools\URL;

/**
 *
 * The defaut administration controller. Basically, display the login form if
 * user is not yet logged in, or back-office home page if the user is logged in.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

abstract class BaseController extends ContainerAware
{
    protected $tokenProvider;

    protected $currentRouter;

    /**
     * Return an empty response (after an ajax request, for example)
     * @param  int                                  $status
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function nullResponse($status = 200)
    {
        return new Response(null, $status);
    }

    /**
     * Return a JSON response
     */
    protected function jsonResponse($json_data, $status = 200)
    {
        return new Response($json_data, $status, array('content-type' => 'application/json'));
    }

    /**
     * @param $pdf
     * @param $fileName
     * @param $status
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function pdfResponse($pdf, $fileName, $status = 200)
    {
        return Response::create($pdf, $status,
            array(
                'Content-type' => "application/pdf",
                'Content-Disposition' => sprintf('Attachment;filename=%s.pdf', $fileName),
            ));
    }

    /**
     * Dispatch a Thelia event
     *
     * @param string $eventName a TheliaEvent name, as defined in TheliaEvents class
     * @param Event  $event     the action event, or null (a DefaultActionEvent will be dispatched)
     */
    protected function dispatch($eventName, ActionEvent $event = null)
    {
        if ($event == null) $event = new DefaultActionEvent();

        $this->getDispatcher()->dispatch($eventName, $event);
    }

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
     *
     * return the Translator
     *
     * @return Translator
     */
    public function getTranslator()
    {
        return $this->container->get('thelia.translator');
    }

    /**
     * Return the parser context,
     *
     * @return ParserContext
     */
    protected function getParserContext()
    {
        return $this->container->get('thelia.parser.context');
    }

    /**
     * Return the security context, by default in admin mode.
     *
     * @return \Thelia\Core\Security\SecurityContext
     */
    protected function getSecurityContext()
    {
        return $this->container->get('thelia.securityContext');
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Request
     */
    protected function getRequest()
    {
        return $this->container->get('request');
    }

    /**
     * Returns the session from the current request
     *
     * @return \Thelia\Core\HttpFoundation\Session\Session
     */
    protected function getSession()
    {
        $request = $this->getRequest();

        return $request->getSession();
    }

    /**
     * @return \Thelia\Tools\TokenProvider
     */
    protected function getTokenProvider()
    {
        if (null === $this->tokenProvider) {
            $this->tokenProvider = $this->container->get("thelia.token_provider");
        }

        return $this->tokenProvider;
    }

    /**
     * Get all errors that occured in a form
     *
     * @param  \Symfony\Component\Form\Form $form
     * @return string                       the error string
     */
    private function getErrorMessages(\Symfony\Component\Form\Form $form)
    {
        $errors = '';

        foreach ($form->getErrors() as $key => $error) {
            $errors .= $error->getMessage() . ', ';
        }

        foreach ($form->all() as $child) {

            if (!$child->isValid()) {

                $fieldName = $child->getConfig()->getOption('label', $child->getName());

                $errors .= sprintf("[%s] %s, ", $fieldName, $this->getErrorMessages($child));
            }
        }

        return rtrim($errors, ', ');
    }

    /**
     * Validate a BaseForm
     *
     * @param  BaseForm                     $aBaseForm      the form
     * @param  string                       $expectedMethod the expected method, POST or GET, or null for any of them
     * @throws FormValidationException      is the form contains error, or the method is not the right one
     * @return \Symfony\Component\Form\Form Form the symfony form object
     */
    protected function validateForm(BaseForm $aBaseForm, $expectedMethod = null)
    {
        $form = $aBaseForm->getForm();

        if ($expectedMethod == null || $aBaseForm->getRequest()->isMethod($expectedMethod)) {

            $form->bind($aBaseForm->getRequest());

            if ($form->isValid()) {
                $env = $this->container->getParameter("kernel.environment");

                if ($aBaseForm instanceof FirewallForm && !$aBaseForm->isFirewallOk($env)) {
                    throw new FormValidationException(
                        $this->getTranslator()->trans(
                            "You've submitted this form too many times. Further submissions will be ignored during %time",
                            [
                                "%time" => $aBaseForm->getWaitingTime(),
                            ]
                        )
                    );
                }

                return $form;
            } else {
                $errorMessage = null;
                if ($form->get("error_message")->getData() != null) {
                    $errorMessage = $form->get("error_message")->getData();
                } else {
                    $errorMessage = sprintf($this->getTranslator()->trans("Missing or invalid data: %s"), $this->getErrorMessages($form));
                }

                throw new FormValidationException($errorMessage);
            }
        } else {
            throw new FormValidationException(sprintf($this->getTranslator()->trans("Wrong form method, %s expected."), $expectedMethod));
        }
    }

    protected function generateOrderPdf($order_id, $fileName)
    {
        $html = $this->renderRaw(
            $fileName,
            array(
                'order_id' => $order_id
            ),
            TemplateHelper::getInstance()->getActivePdfTemplate()
        );

        $order = OrderQuery::create()->findPk($order_id);

        try {
            $pdfEvent = new PdfEvent($html);

            $this->dispatch(TheliaEvents::GENERATE_PDF, $pdfEvent);

            if ($pdfEvent->hasPdf()) {
                return $this->pdfResponse($pdfEvent->getPdf(), $order->getRef());
            }

        } catch (\Exception $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf('error during generating invoice pdf for order id : %d with message "%s"', $order_id, $e->getMessage()));

        }

    }

    /**
     *
     * redirect request to the specified url
     *
     * @param string $url
     * @param int    $status  http status. Must be a 30x status
     * @param array  $cookies
     *
     * @deprecated redirect is deprecated since version 2.1 and will be removed in 2.3. You must return an instance of \Symfony\Component\HttpFoundation\RedirectResponse insteand of send a response.
     */
    public function redirect($url, $status = 302, $cookies = array())
    {
        trigger_error('redirect is deprecated since version 2.1 and will be removed in 2.3. You must return an instance of \Symfony\Component\HttpFoundation\RedirectResponse insteand of send a response. ', E_USER_DEPRECATED);

        Redirect::exec($url, $status, $cookies);
    }

    /**
     * Redirect to à route ID related URL
     *
     * @param string $routeId         the route ID, as found in Config/Resources/routing/admin.xml
     * @param array  $urlParameters   the URL parameters, as a var/value pair array
     * @param array  $routeParameters
     */
    public function redirectToRoute($routeId, array $urlParameters = array(), array $routeParameters = array())
    {
        $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute($routeId, $routeParameters), $urlParameters));
    }

    /**
     * If success_url param is present in request or in the provided form, redirect to this URL.
     *
     * @param BaseForm $form a base form, which may contains the success URL
     *
     * @deprecated redirectSuccess is deprecated since version 2.1 and will be removed in 2.3. You must return an instance of \Symfony\Component\HttpFoundation\RedirectResponse insteand of send a response.
     */
    protected function redirectSuccess(BaseForm $form = null)
    {
        trigger_error('redirectSuccess is deprecated since version 2.1 and will be removed in 2.3. You must return an instance of \Symfony\Component\HttpFoundation\RedirectResponse insteand of send a response. ', E_USER_DEPRECATED);

        if ($form != null) {
            $url = $form->getSuccessUrl();
        } else {
            $url = $this->getRequest()->get("success_url");
        }

        if (null !== $url) $this->redirect($url);
    }

    /**
     * Search success url in a form if present, in the query string otherwise
     *
     * @param BaseForm $form
     * @return mixed|null|string
     */
    protected function retrieveSuccessUrl(BaseForm $form = null)
    {
        $url = null;
        if ($form != null) {
            $url = $form->getSuccessUrl();
        } else {
            $url = $this->getRequest()->get("success_url");
        }

        return $url;
    }

    protected function retrieveUrlFromRouteId($routeId, array $urlParameters = [], array $routeParameters = [], $referenceType = Router::ABSOLUTE_PATH)
    {
        return URL::getInstance()->absoluteUrl($this->getRoute($routeId, $routeParameters, $referenceType), $urlParameters);
    }

    /**
     *
     * create an instance of RedirectResponse
     *
     * @param $url
     * @param int $status
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function generateRedirect($url, $status = 302)
    {
        return RedirectResponse::create($url, $status);
    }

    /**
     * create an instance of RedirectReponse if a success url is present, return null otherwise
     *
     * @param BaseForm $form
     * @return null|\Symfony\Component\HttpFoundation\Response
     */
    protected function generateSuccessRedirect(BaseForm $form = null)
    {
        $response = null;
        if (null !== $url = $this->retrieveSuccessUrl($form)) {
            $response = $this->generateRedirect($url);
        }

        return $response;
    }

    protected function generateRedirectFromRoute($routeId, array $urlParameters = [], array $routeParameters = [], $referenceType = Router::ABSOLUTE_PATH)
    {
        return $this->generateRedirect(
            $this->retrieveUrlFromRouteId($routeId, $urlParameters, $routeParameters, $referenceType)
        );
    }


    /**
     * Return the route path defined for the givent route ID
     *
     * @param string         $routeId       a route ID, as defines in Config/Resources/routing/admin.xml
     * @param mixed          $parameters    An array of parameters
     * @param Boolean|string $referenceType The type of reference to be generated (one of the constants)
     *
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     * @throws \InvalidArgumentException           When the router doesn't exist
     * @return string                              The generated URL
     *
     * @see \Thelia\Controller\BaseController::getRouteFromRouter()
     */
    protected function getRoute($routeId, $parameters = array(), $referenceType = Router::ABSOLUTE_URL)
    {
        return $this->getRouteFromRouter(
            $this->getCurrentRouter(),
            $routeId,
            $parameters,
            $referenceType
        );
    }



    /**
     * Get a route path from the route id.
     *
     * @param string         $routerName    Router name
     * @param string         $routeId       The name of the route
     * @param mixed          $parameters    An array of parameters
     * @param Boolean|string $referenceType The type of reference to be generated (one of the constants)
     *
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     * @throws \InvalidArgumentException           When the router doesn't exist
     * @return string                              The generated URL
     */
    protected function getRouteFromRouter($routerName, $routeId, $parameters = array(), $referenceType = Router::ABSOLUTE_URL)
    {
        /** @var Router $router */
        $router =  $this->getRouter($routerName);

        if ($router == null) {
            throw new \InvalidArgumentException(sprintf("Router '%s' does not exists.", $routerName));
        }

        return $router->generate($routeId, $parameters, $referenceType);
    }

    /**
     * @param $routerName
     * @return Router
     */
    protected function getRouter($routerName)
    {
        return $this->container->get($routerName);
    }

    /**
     * Return a 404 error
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function pageNotFound()
    {
        throw new NotFoundHttpException();
    }

    /**
     * Check if environment is in debug mode
     *
     * @return bool
     */
    protected function isDebug()
    {
        return $this->container->getParameter('kernel.debug');
    }

    protected function accessDenied()
    {
        throw new AccessDeniedHttpException();
    }

    /**
     * check if the current http request is a XmlHttpRequest.
     *
     * If not, send a
     */
    protected function checkXmlHttpRequest()
    {
        if (false === $this->getRequest()->isXmlHttpRequest() && false === $this->isDebug()) {
            $this->accessDenied();
        }
    }

    /**
     *
     * return an instance of \Swift_Mailer with good Transporter configured.
     *
     * @return \Swift_Mailer
     */
    public function getMailer()
    {
        $mailer = $this->container->get('mailer');

        return $mailer->getSwiftMailer();
    }

    protected function getCurrentRouter()
    {
        return $this->currentRouter;
    }

    protected function setCurrentRouter($routerId)
    {
        $this->currentRouter = $routerId;
    }

    /**
     * @return a ParserInterface instance parser
     */
    abstract protected function getParser($template = null);

    /**
     * Render the given template, and returns the result as an Http Response.
     *
     * @param $templateName the complete template name, with extension
     * @param  array                                $args   the template arguments
     * @param  int                                  $status http code status
     * @return \Thelia\Core\HttpFoundation\Response
     */
    abstract protected function render($templateName, $args = array(), $status = 200);

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param $templateName the complete template name, with extension
     * @param array $args        the template arguments
     * @param null  $templateDir
     *
     * @return string
     */
    abstract protected function renderRaw($templateName, $args = array(), $templateDir = null);
}
