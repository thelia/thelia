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

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Router;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\OrderQuery;
use Thelia\Tools\Redirect;
use Thelia\Tools\URL;

/**
 *
 * The defaut administration controller. Basically, display the login form if
 * user is not yet logged in, or back-office home page if the user is logged in.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Benjamin Perche <bperche@openstudio.fr>
 */

abstract class BaseController extends ContainerAware
{
    const EMPTY_FORM_NAME = "thelia.empty";

    protected $tokenProvider;

    protected $currentRouter;

    protected $translator;

    protected $templateHelper;

    /** @var bool Fallback on default template when setting the templateDefinition */
    protected $useFallbackTemplate = false;

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
     * @param $jsonData
     * @param int $status
     * @return Response Return a JSON response
     */
    protected function jsonResponse($jsonData, $status = 200)
    {
        return new Response($jsonData, $status, array('content-type' => 'application/json'));
    }

    /**
     * @param $pdf
     * @param $fileName
     * @param $status
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function pdfResponse($pdf, $fileName, $status = 200, $browser = false)
    {
        return Response::create(
            $pdf,
            $status,
            array(
                'Content-type' => "application/pdf",
                'Content-Disposition' => $browser == false ? sprintf('Attachment;filename=%s.pdf', $fileName) : '',
            )
        );
    }

    /**
     * Dispatch a Thelia event
     *
     * @param string $eventName a TheliaEvent name, as defined in TheliaEvents class
     * @param ActionEvent  $event     the action event, or null (a DefaultActionEvent will be dispatched)
     */
    protected function dispatch($eventName, ActionEvent $event = null)
    {
        if ($event == null) {
            $event = new DefaultActionEvent();
        }

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
     * return the Translator
     *
     * @return Translator
     */
    public function getTranslator()
    {
        if (null === $this->translator) {
            $this->translator = $this->container->get('thelia.translator');
        }
        return $this->translator;
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
     * @return \Thelia\Core\Template\TemplateHelperInterface
     */
    protected function getTemplateHelper()
    {
        if (null === $this->templateHelper) {
            $this->templateHelper = $this->container->get("thelia.template_helper");
        }

        return $this->templateHelper;
    }

    /**
     * Get all errors that occurred in a form
     *
     * @param  \Symfony\Component\Form\Form $form
     * @return string                       the error string
     */
    protected function getErrorMessages(Form $form)
    {
        return $this->getTheliaFormValidator()->getErrorMessages($form);
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
        $form = $this->getTheliaFormValidator()->validateForm($aBaseForm, $expectedMethod);

        // At this point, the form is valid (no exception was thrown). Remove it from the error context.
        $this->getParserContext()->clearForm($aBaseForm);

        return $form;
    }

    /**
     * @return \Thelia\Core\Form\TheliaFormValidatorInterface
     */
    protected function getTheliaFormValidator()
    {
        return $this->container->get("thelia.form_validator");
    }

    /**
     * @param int $order_id
     * @param string $fileName
     * @param bool $checkOrderStatus
     * @param bool $checkAdminUser
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function generateOrderPdf($order_id, $fileName, $checkOrderStatus = true, $checkAdminUser = true, $browser = false)
    {
        $order = OrderQuery::create()->findPk($order_id);

        // check if the order has the paid status
        if ($checkAdminUser && !$this->getSecurityContext()->hasAdminUser()) {
            if ($checkOrderStatus && !$order->isPaid(false)) {
                throw new NotFoundHttpException();
            }
        }

        $html = $this->renderRaw(
            $fileName,
            array(
                'order_id' => $order_id
            ),
            $this->getTemplateHelper()->getActivePdfTemplate()
        );

        try {
            $pdfEvent = new PdfEvent($html);

            $this->dispatch(TheliaEvents::GENERATE_PDF, $pdfEvent);

            if ($pdfEvent->hasPdf()) {
                return $this->pdfResponse($pdfEvent->getPdf(), $order->getRef(), 200, $browser);
            }
        } catch (\Exception $e) {
            Tlog::getInstance()->error(
                sprintf(
                    'error during generating invoice pdf for order id : %d with message "%s"',
                    $order_id,
                    $e->getMessage()
                )
            );
        }

        throw new TheliaProcessException(
            $this->getTranslator()->trans(
                "We're sorry, this PDF invoice is not available at the moment."
            )
        );
    }

    /**
     * Search success url in a form if present, in the query string otherwise
     *
     * @param  BaseForm          $form
     * @return mixed|null|string
     */
    protected function retrieveSuccessUrl(BaseForm $form = null)
    {
        return $this->retrieveFormBasedUrl('success_url', $form);
    }

    /**
     * Search error url in a form if present, in the query string otherwise
     *
     * @param  BaseForm          $form
     * @return mixed|null|string
     */
    protected function retrieveErrorUrl(BaseForm $form = null)
    {
        return $this->retrieveFormBasedUrl('error_url', $form);
    }
    /**
     * Search url in a form parameter, or in a request parameter.
     *
     * @param  string $parameterName the form parameter name, or request parameter name.
     * @param  BaseForm  $form the form
     * @return mixed|null|string
     */
    protected function retrieveFormBasedUrl($parameterName, BaseForm $form = null)
    {
        $url = null;

        if ($form != null) {
            $url = $form->getFormDefinedUrl($parameterName);
        } else {
            $url = $this->getRequest()->get($parameterName);
        }

        return $url;
    }

    /**
     * @param $routeId
     * @param array $urlParameters
     * @param array $routeParameters
     * @param bool $referenceType
     * @return string
     */
    protected function retrieveUrlFromRouteId(
        $routeId,
        array $urlParameters = [],
        array $routeParameters = [],
        $referenceType = Router::ABSOLUTE_PATH
    ) {
        return URL::getInstance()->absoluteUrl(
            $this->getRoute(
                $routeId,
                $routeParameters,
                $referenceType
            ),
            $urlParameters
        );
    }

    /**
     *
     * create an instance of RedirectResponse
     *
     * @param $url
     * @param  int                                        $status
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function generateRedirect($url, $status = 302)
    {
        return RedirectResponse::create($url, $status);
    }

    /**
     * create an instance of RedirectReponse if a success url is present, return null otherwise
     *
     * @param  BaseForm                                        $form
     * @return null|\Symfony\Component\HttpFoundation\Response
     */
    protected function generateSuccessRedirect(BaseForm $form = null)
    {
        if (null !== $url = $this->retrieveSuccessUrl($form)) {
            return $this->generateRedirect($url);
        }

        return null;
    }

    /**
     * create an instance of RedirectReponse if a success url is present, return null otherwise
     *
     * @param  BaseForm                                        $form
     * @return null|\Symfony\Component\HttpFoundation\Response
     */
    protected function generateErrorRedirect(BaseForm $form = null)
    {
        if (null !== $url = $this->retrieveErrorUrl($form)) {
            return $this->generateRedirect($url);
        }

        return null;
    }

    /**
     *
     * create an instance of RedriectResponse for a given route id.
     *
     * @param $routeId
     * @param  array                                      $urlParameters
     * @param  array                                      $routeParameters
     * @param  bool                                       $referenceType
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function generateRedirectFromRoute(
        $routeId,
        array $urlParameters = [],
        array $routeParameters = [],
        $referenceType = Router::ABSOLUTE_PATH
    ) {
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
    protected function getRouteFromRouter(
        $routerName,
        $routeId,
        $parameters = array(),
        $referenceType = Router::ABSOLUTE_URL
    ) {
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
     * @return MailerFactory
     */
    public function getMailer()
    {
        return $this->container->get('mailer');
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
     * @param $name
     * @param $type
     * @param array $data
     * @param array $options
     * @return BaseForm
     *
     * This method builds a thelia form with its name
     */
    public function createForm($name, $type = "form", array $data = array(), array $options = array())
    {
        if (empty($name)) {
            $name = static::EMPTY_FORM_NAME;
        }

        return $this->getTheliaFormFactory()->createForm($name, $type, $data, $options);
    }

    /**
     * @return \Thelia\Core\Form\TheliaFormFactoryInterface
     */
    protected function getTheliaFormFactory()
    {
        return $this->container->get("thelia.form_factory");
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Return controller type
     *
     * @return string
     */
    abstract public function getControllerType();

    /**
     * @param null|mixed $template
     * @return \Thelia\Core\Template\ParserInterface instance parser
     */
    abstract protected function getParser($template = null);

    /**
     * Render the given template, and returns the result as an Http Response.
     *
     * @param string $templateName the complete template name, with extension
     * @param  array                                $args   the template arguments
     * @param  int                                  $status http code status
     * @return \Thelia\Core\HttpFoundation\Response
     */
    abstract protected function render($templateName, $args = array(), $status = 200);

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param string $templateName the complete template name, with extension
     * @param array $args        the template arguments
     * @param null  $templateDir
     *
     * @return string
     */
    abstract protected function renderRaw($templateName, $args = array(), $templateDir = null);
}
