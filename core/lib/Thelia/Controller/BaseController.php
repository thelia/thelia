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

namespace Thelia\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Router;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Form\TheliaFormValidator;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Exception\TheliaProcessException;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\OrderQuery;
use Thelia\Tools\TokenProvider;
use Thelia\Tools\URL;

/**
 * The defaut administration controller. Basically, display the login form if
 * user is not yet logged in, or back-office home page if the user is logged in.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class BaseController implements ControllerInterface
{
    public const EMPTY_FORM_NAME = 'thelia.empty';

    #[Required]
    public TokenProvider $tokenProvider;

    #[Required]
    public ParserResolver $parserResolver;

    #[Required]
    public RequestStack $requestStack;

    #[Required]
    public SecurityContext $securityContext;

    #[Required]
    public TranslatorInterface $translator;

    #[Required]
    public TemplateHelperInterface $templateHelper;

    #[Required]
    public ParserContext $parserContext;

    #[Required]
    public AdminResources $adminResources;

    #[Required]
    public TheliaFormValidator $theliaFormValidator;

    #[Required]
    public ParameterBagInterface $parameterBag;

    #[Required]
    public TheliaFormFactory $theliaFormFactory;

    #[Required]
    public ContainerInterface $container;

    #[Required]
    public MailerInterface $mailer;

    protected string $currentRouter;

    /** @var bool Fallback on default template when setting the templateDefinition */
    protected bool $useFallbackTemplate = true;

    /**
     * Return an empty response (after an ajax request, for example).
     *
     * @param int $status
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function nullResponse($status = 200)
    {
        return new Response(null, $status);
    }

    /**
     * @param int $status
     *
     * @return Response Return a JSON response
     */
    protected function jsonResponse($jsonData, $status = 200)
    {
        return new Response($jsonData, $status, ['content-type' => 'application/json']);
    }

    /**
     * @param int  $status
     * @param bool $browser
     */
    protected function pdfResponse($pdf, $fileName, $status = 200, $browser = false)
    {
        return new Response(
            $pdf,
            $status,
            [
                'Content-type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    '%s; filename=%s.pdf',
                    (bool) $browser === false ? 'attachment' : 'inline',
                    $fileName
                ),
            ]
        );
    }

    /**
     * Dispatch a Thelia event.
     *
     * @param string           $eventName a TheliaEvent name, as defined in TheliaEvents class
     * @param ActionEvent|null $event     the action event, or null (a DefaultActionEvent will be dispatched)
     *
     * Not allowed since Thelia 2.5, use autowiring instead.
     */
    protected function dispatch(string $eventName, Event $event = null): void
    {
        throw new \Exception('Since Thelia 2.5 this->dispatch() function is not allowed in controllers, use autowiring instead');
    }

    /**
     * Return the event dispatcher,.
     *
     * @return EventDispatcher
     *
     * @deprecated since Thelia 2.5, use autowiring instead.
     */
    public function getDispatcher()
    {
        throw new \Exception('Since Thelia 2.5 this->getDispatcher() function is not allowed in controllers, use autowiring instead');
    }

    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    protected function getParserContext(): ParserContext
    {
        return $this->parserContext;
    }

    protected function getSecurityContext(): SecurityContext
    {
        return $this->securityContext;
    }

    protected function getRequest(): \Symfony\Component\HttpFoundation\Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    protected function getSession(): SessionInterface
    {
        return $this->getRequest()->getSession();
    }

    protected function getTokenProvider(): TokenProvider
    {
        return $this->tokenProvider;
    }

    protected function getTemplateHelper(): TemplateHelperInterface
    {
        return $this->templateHelper;
    }

    protected function getAdminResources(): AdminResources
    {
        return $this->adminResources;
    }

    protected function getErrorMessages(Form $form): string
    {
        return $this->getTheliaFormValidator()->getErrorMessages($form);
    }

    /**
     * Validate a BaseForm.
     *
     * @param BaseForm $aBaseForm      the form
     * @param string   $expectedMethod the expected method, POST or GET, or null for any of them
     *
     * @throws FormValidationException is the form contains error, or the method is not the right one
     *
     * @return \Symfony\Component\Form\Form Form the symfony form object
     */
    protected function validateForm(BaseForm $aBaseForm, $expectedMethod = null)
    {
        $form = $this->getTheliaFormValidator()->validateForm($aBaseForm, $expectedMethod);

        // At this point, the form is valid (no exception was thrown). Remove it from the error context.
        $this->getParserContext()->clearForm($aBaseForm);

        return $form;
    }

    protected function getTheliaFormValidator(): TheliaFormValidator
    {
        return $this->theliaFormValidator;
    }

    protected function generateOrderPdf(
        EventDispatcherInterface $eventDispatcher,
        int $order_id,
        string $fileName,
        bool $checkOrderStatus = true,
        bool $checkAdminUser = true,
        $browser = false
    ): Response {
        $order = OrderQuery::create()->findPk($order_id);

        // check if the order has the paid status
        if ($checkAdminUser && $checkOrderStatus && !$order->isPaid(false) && !$this->getSecurityContext()->hasAdminUser()) {
            throw new NotFoundHttpException();
        }

        $html = $this->renderRaw(
            $fileName,
            [
                'order_id' => $order_id,
            ],
            $this->getTemplateHelper()->getActivePdfTemplate()
        );

        try {
            $pdfEvent = new PdfEvent($html);
            $pdfEvent->setTemplateName($fileName);
            $pdfEvent->setFileName($order->getRef());
            $pdfEvent->setObject($order);

            $eventDispatcher->dispatch($pdfEvent, TheliaEvents::GENERATE_PDF);

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
     * Search success url in a form if present, in the query string otherwise.
     *
     * @return mixed|string|null
     */
    protected function retrieveSuccessUrl(BaseForm $form = null)
    {
        return $this->retrieveFormBasedUrl('success_url', $form);
    }

    /**
     * Search error url in a form if present, in the query string otherwise.
     *
     * @return mixed|string|null
     */
    protected function retrieveErrorUrl(BaseForm $form = null)
    {
        return $this->retrieveFormBasedUrl('error_url', $form);
    }

    /**
     * Search url in a form parameter, or in a request parameter.
     */
    protected function retrieveFormBasedUrl(string $parameterName, BaseForm $form = null)
    {
        $url = null;

        if ($form !== null) {
            $url = $form->getFormDefinedUrl($parameterName);
        } else {
            $url = $this->requestStack->getCurrentRequest()?->get($parameterName);
        }

        return $url;
    }

    /**
     * @param int $referenceType
     *
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
     * create an instance of RedirectResponse.
     *
     * @param int $status
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function generateRedirect($url, $status = 302)
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * create an instance of RedirectReponse if a success url is present, return null otherwise.
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    protected function generateSuccessRedirect(BaseForm $form = null)
    {
        if (null !== $url = $this->retrieveSuccessUrl($form)) {
            return $this->generateRedirect($url);
        }

        return null;
    }

    /**
     * create an instance of RedirectReponse if a success url is present, return null otherwise.
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    protected function generateErrorRedirect(BaseForm $form = null)
    {
        if (null !== $url = $this->retrieveErrorUrl($form)) {
            return $this->generateRedirect($url);
        }

        return null;
    }

    /**
     * create an instance of RedriectResponse for a given route id.
     *
     * @param int $referenceType
     *
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
     * Return the route path defined for the givent route ID.
     *
     * @param string $routeId       a route ID, as defines in Config/Resources/routing/admin.xml
     * @param mixed  $parameters    An array of parameters
     * @param int    $referenceType The type of reference to be generated (one of the constants)
     *
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     * @throws \InvalidArgumentException           When the router doesn't exist
     *
     * @return string The generated URL
     *
     * @see \Thelia\Controller\BaseController::getRouteFromRouter()
     */
    protected function getRoute($routeId, $parameters = [], $referenceType = Router::ABSOLUTE_URL)
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
     * @param string $routerName    Router name
     * @param string $routeId       The name of the route
     * @param mixed  $parameters    An array of parameters
     * @param int    $referenceType The type of reference to be generated (one of the constants)
     *
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     * @throws \InvalidArgumentException           When the router doesn't exist
     *
     * @return string The generated URL
     */
    protected function getRouteFromRouter(
        $routerName,
        $routeId,
        $parameters = [],
        $referenceType = Router::ABSOLUTE_URL
    ) {
        /** @var Router $router */
        $router = $this->getRouter($routerName);

        if ($router == null) {
            throw new \InvalidArgumentException(sprintf("Router '%s' does not exists.", $routerName));
        }

        return $router->generate($routeId, $parameters, $referenceType);
    }

    protected function getRouter($routerName): ?object
    {
        return $this->container->get($routerName);
    }

    /**
     * Return a 404 error.
     */
    protected function pageNotFound()
    {
        throw new NotFoundHttpException();
    }

    /**
     * Check if environment is in debug mode.
     */
    protected function isDebug(): bool
    {
        return (bool) $this->parameterBag->get('kernel.debug');
    }

    protected function accessDenied(): void
    {
        throw new AccessDeniedHttpException();
    }

    /**
     * check if the current http request is a XmlHttpRequest.
     *
     * If not, send a
     */
    protected function checkXmlHttpRequest(): void
    {
        if (false === $this->requestStack->getCurrentRequest()?->isXmlHttpRequest() && false === $this->isDebug()) {
            $this->accessDenied();
        }
    }

    public function getMailer(): MailerInterface
    {
        return $this->mailer;
    }

    protected function getCurrentRouter(): string
    {
        return $this->currentRouter;
    }

    protected function setCurrentRouter($routerId): void
    {
        $this->currentRouter = $routerId;
    }

    /**
     * @return BaseForm
     *
     * This method builds a thelia form with its name
     */
    public function createForm($name, $type = FormType::class, array $data = [], array $options = []): BaseForm
    {
        if (empty($name)) {
            $name = static::EMPTY_FORM_NAME;
        }

        return $this->theliaFormFactory->createForm($name, $type, $data, $options);
    }

    protected function getTheliaFormFactory(): TheliaFormFactory
    {
        return $this->theliaFormFactory;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Return controller type.
     */
    abstract public function getControllerType(): string;

    /**
     * @param mixed|null $template
     *
     * @return \Thelia\Core\Template\ParserInterface instance parser
     */
    abstract protected function getParser($template = null);

    /**
     * Render the given template, and returns the result as an Http Response.
     *
     * @param string $templateName the complete template name, with extension
     * @param array  $args         the template arguments
     * @param int    $status       http code status
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    abstract protected function render($templateName, $args = [], $status = 200);

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param string $templateName the complete template name, with extension
     * @param array  $args         the template arguments
     * @param null   $templateDir
     *
     * @return string
     */
    abstract protected function renderRaw($templateName, $args = [], $templateDir = null);
}
