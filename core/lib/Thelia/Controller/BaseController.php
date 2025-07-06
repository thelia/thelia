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

namespace Thelia\Controller;

use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Form\TheliaFormValidator;
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

    abstract public function getControllerType(): string;

    abstract protected function getParser(?string $template = null);

    abstract protected function render(string $templateName, array $args = [], int $status = 200): Response;

    abstract protected function renderRaw(string $templateName, array $args = [], ?string $templateDir = null): string;

    protected function nullResponse(int $status = 200): Response
    {
        return new Response(null, $status);
    }

    protected function jsonResponse(?string $jsonData, int $status = 200): Response
    {
        return new Response($jsonData, $status, ['content-type' => 'application/json']);
    }

    protected function pdfResponse(?string $pdf, string $fileName, int $status = 200, bool $browser = false): Response
    {
        return new Response(
            $pdf,
            $status,
            [
                'Content-type' => 'application/pdf',
                'Content-Disposition' => \sprintf(
                    '%s; filename=%s.pdf',
                    false === $browser ? 'attachment' : 'inline',
                    $fileName,
                ),
            ],
        );
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

    protected function getRequest(): Request
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
     * @throws FormValidationException is the form contains error, or the method is not the right one
     */
    protected function validateForm(BaseForm $aBaseForm, ?string $expectedMethod = null): Form
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
        bool $browser = false,
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
            $this->getTemplateHelper()->getActivePdfTemplate(),
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
        } catch (\Exception $exception) {
            Tlog::getInstance()->error(
                \sprintf(
                    'error during generating invoice pdf for order id : %d with message "%s"',
                    $order_id,
                    $exception->getMessage(),
                ),
            );
        }

        throw new TheliaProcessException($this->getTranslator()->trans("We're sorry, this PDF invoice is not available at the moment."));
    }

    protected function retrieveSuccessUrl(?BaseForm $form = null): mixed
    {
        return $this->retrieveFormBasedUrl('success_url', $form);
    }

    protected function retrieveErrorUrl(?BaseForm $form = null): mixed
    {
        return $this->retrieveFormBasedUrl('error_url', $form);
    }

    protected function retrieveFormBasedUrl(string $parameterName, ?BaseForm $form = null)
    {
        $url = null;

        if ($form instanceof BaseForm) {
            $url = $form->getFormDefinedUrl($parameterName);
        } else {
            $url = $this->requestStack->getCurrentRequest()?->get($parameterName);
        }

        return $url;
    }

    protected function retrieveUrlFromRouteId(
        string $routeId,
        array $urlParameters = [],
        array $routeParameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        return URL::getInstance()->absoluteUrl(
            $this->getRoute(
                $routeId,
                $routeParameters,
                $referenceType,
            ),
            $urlParameters,
        );
    }

    protected function generateRedirect($url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    protected function generateSuccessRedirect(?BaseForm $form = null): ?RedirectResponse
    {
        if (null !== $url = $this->retrieveSuccessUrl($form)) {
            return $this->generateRedirect($url);
        }

        return null;
    }

    protected function generateErrorRedirect(?BaseForm $form = null): ?RedirectResponse
    {
        if (null !== $url = $this->retrieveErrorUrl($form)) {
            return $this->generateRedirect($url);
        }

        return null;
    }

    protected function generateRedirectFromRoute(
        string $routeId,
        array $urlParameters = [],
        array $routeParameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): RedirectResponse {
        return $this->generateRedirect(
            $this->retrieveUrlFromRouteId($routeId, $urlParameters, $routeParameters, $referenceType),
        );
    }

    /**
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     * @throws \InvalidArgumentException           When the router doesn't exist
     *
     * @see \Thelia\Controller\BaseController::getRouteFromRouter()
     */
    protected function getRoute(string $routeId, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): string
    {
        return $this->getRouteFromRouter(
            $this->getCurrentRouter(),
            $routeId,
            $parameters,
            $referenceType,
        );
    }

    /**
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     * @throws \InvalidArgumentException           When the router doesn't exist
     */
    protected function getRouteFromRouter(
        string $routerName,
        string $routeId,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL,
    ): string {
        /** @var Router $router */
        $router = $this->getRouter($routerName);

        if (null === $router) {
            throw new \InvalidArgumentException(\sprintf("Router '%s' does not exists.", $routerName));
        }

        return $router->generate($routeId, $parameters, $referenceType);
    }

    protected function getRouter(string $routerName): ?object
    {
        return $this->container->get($routerName);
    }

    protected function pageNotFound(): ?Response
    {
        throw new NotFoundHttpException();
    }

    protected function isDebug(): bool
    {
        return (bool) $this->parameterBag->get('kernel.debug');
    }

    protected function accessDenied(): void
    {
        throw new AccessDeniedHttpException();
    }

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

    protected function setCurrentRouter(string $routerId): void
    {
        $this->currentRouter = $routerId;
    }

    public function createForm($name, string $type = FormType::class, array $data = [], array $options = []): BaseForm
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
}
