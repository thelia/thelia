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

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Controller\BaseController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\AdminLog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

class BaseAdminController extends BaseController
{
    public const CONTROLLER_TYPE = 'admin';
    public const TEMPLATE_404 = '404';

    protected string $currentRouter = 'router.admin';

    public function processTemplateAction(string $template): Response|RedirectResponse|string
    {
        try {
            if ('' !== $template && '0' !== $template) {
                // If we have a view in the URL, render this view
                return $this->render($template);
            }

            if (null !== $view = $this->requestStack->getMainRequest()?->get('view')) {
                return $this->render($view);
            }
        } catch (\Exception $exception) {
            return $this->errorPage($exception->getMessage());
        }

        return $this->pageNotFound();
    }

    public function getControllerType(): string
    {
        return self::CONTROLLER_TYPE;
    }

    protected function adminLogAppend(string $resource, string $action, string $message, ?int $resourceId = null): void
    {
        AdminLog::append(
            $resource,
            $action,
            $message,
            $this->requestStack->getMainRequest(),
            $this->securityContext->getAdminUser(),
            true,
            $resourceId,
        );
    }

    protected function pageNotFound(): Response
    {
        return new Response($this->renderRaw(self::TEMPLATE_404), Response::HTTP_NOT_FOUND);
    }

    protected function errorPage(\Exception|string $message, int $status = 500): Response
    {
        if ($message instanceof \Exception) {
            $strMessage = $this->translator->trans(
                'Sorry, an error occured: %msg',
                ['%msg' => $message->getMessage()],
            );

            Tlog::getInstance()->addError($strMessage.': '.$message->getTraceAsString());

            $message = $strMessage;
        } else {
            Tlog::getInstance()->addError($message);
        }

        return $this->render(
            'general_error',
            [
                'error_message' => $message,
            ],
            $status,
        );
    }

    protected function checkAuth(mixed $resources, mixed $modules, mixed $accesses): ?Response
    {
        $request = $this->requestStack->getMainRequest();
        if (!$request instanceof Request) {
            return null;
        }
        $resources = \is_array($resources) ? $resources : [$resources];
        $modules = \is_array($modules) ? $modules : [$modules];
        $accesses = \is_array($accesses) ? $accesses : [$accesses];

        if ($this->securityContext->isGranted(['ADMIN'], $resources, $modules, $accesses)) {
            // Okay !
            return null;
        }

        // Log the problem
        $this->adminLogAppend(implode(',', $resources), implode(',', $accesses), 'User is not granted for resources %s with accesses %s');

        return $this->errorPage($this->translator->trans("Sorry, you're not allowed to perform this action"), 403);
    }

    protected function createStandardFormValidationErrorMessage(FormValidationException $exception): string
    {
        return $this->translator->trans(
            'Please check your input: %error',
            [
                '%error' => $exception->getMessage(),
            ],
        );
    }

    protected function setupFormErrorContext(string $action, string $error_message, ?BaseForm $form = null, ?\Exception $exception = null): void
    {
        // Log the error message
        Tlog::getInstance()->error(
            $this->translator->trans(
                'Error during %action process : %error. Exception was %exc',
                [
                    '%action' => $action,
                    '%error' => $error_message,
                    '%exc' => $exception instanceof \Exception ? $exception->getMessage() : 'no exception',
                ],
            ),
        );

        if ($form instanceof BaseForm) {
            // Mark the form as errored
            $form->setErrorMessage($error_message);

            // Pass it to the parser context
            $this->getParserContext()->addForm($form);
        }

        // Pass the error message to the parser.
        $this->getParserContext()->setGeneralError($error_message);
    }

    protected function getParser(?string $template = null): ParserInterface
    {
        $path = $this->templateHelper->getActiveAdminTemplate()->getAbsolutePath();
        $parser = $this->parserResolver->getParser($path, $template);
        // Define the template that should be used
        $parser->setTemplateDefinition(
            $parser->getTemplateDefinition() ?: $this->templateHelper->getActiveAdminTemplate(),
            $this->useFallbackTemplate,
        );

        return $parser;
    }

    protected function forward(string $controller, array $path = [], array $query = []): Response
    {
        $path['_controller'] = $controller;
        $subRequest = $this->requestStack->getMainRequest()?->duplicate($query, null, $path);

        return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    protected function getCurrentEditionCurrency()
    {
        // Return the new language if a change is required.
        if (null !== ($edit_currency_id = $this->requestStack->getMainRequest()?->get('edit_currency_id')) && null !== $edit_currency = CurrencyQuery::create()->findOneById($edit_currency_id)) {
            return $edit_currency;
        }

        // Otherwise return the lang stored in session.
        return $this->getSession()->getAdminEditionCurrency();
    }

    protected function getCurrentEditionLang()
    {
        // Return the new language if a change is required.
        if (null !== ($edit_language_id = $this->requestStack->getMainRequest()?->get('edit_language_id')) && null !== $edit_language = LangQuery::create()->findOneById($edit_language_id)) {
            return $edit_language;
        }

        // Otherwise return the lang stored in session.
        return $this->getSession()->getAdminEditionLang();
    }

    protected function getCurrentEditionLocale()
    {
        return $this->getCurrentEditionLang()?->getLocale();
    }

    protected function getUrlLanguage(?string $locale = null): ?string
    {
        // Check if the functionality is activated
        if (!ConfigQuery::isMultiDomainActivated()) {
            return null;
        }

        // If we don't have a locale value, use the locale value in the session
        if (!$locale) {
            $locale = $this->getCurrentEditionLocale();
        }

        return LangQuery::create()->findOneByLocale($locale)?->getUrl();
    }

    protected function getListOrderFromSession(
        string $objectName,
        ?string $requestParameterName,
        ?string $defaultListOrder,
        bool $updateSession = true,
    ): ?string {
        $orderSessionIdentifier = \sprintf('admin.%s.currentListOrder', $objectName);

        if (null === $requestParameterName || null === $defaultListOrder) {
            return null;
        }

        // Find the current order
        $order = $this->requestStack->getMainRequest()?->get(
            $requestParameterName,
            $this->getSession()->get($orderSessionIdentifier, $defaultListOrder),
        );

        if ($updateSession) {
            $this->getSession()->set($orderSessionIdentifier, $order);
        }

        return $order;
    }

    protected function render(string $templateName, array $args = [], int $status = 200): Response
    {
        $response = $this->renderRaw($templateName, $args);

        return new Response($response, $status);
    }

    protected function renderRaw(string $templateName, array $args = [], $templateDir = null): string
    {
        $parser = $this->getParser($templateDir.'/'.$templateName);

        // Add the template standard extension
        $templateName .= '.'.$parser->getFileExtension();

        // Find the current edit language ID
        $editionLang = $this->getCurrentEditionLang();

        // Find the current edit currency ID
        $editionCurrency = $this->getCurrentEditionCurrency();

        // Prepare common template variables
        $args = array_merge($args, [
            'edit_language_id' => $editionLang?->getId(),
            'edit_language_locale' => $editionLang?->getLocale(),
            'edit_currency_id' => $editionCurrency?->getId(),
        ]);

        // Update the current edition language & currency in session
        $this->getSession()
            ->setAdminEditionLang($editionLang)
            ->setAdminEditionCurrency($editionCurrency);

        // Render the template.
        try {
            $content = $parser->render($templateName, $args);
        } catch (AuthenticationException $ex) {
            // User is not authenticated, and templates requires authentication -> redirect to login page
            // We user login_tpl as a path, not a template.
            throw new RedirectException(URL::getInstance()->absoluteUrl($ex->getLoginTemplate()));
        } catch (AuthorizationException) {
            // User is not allowed to perform the required action. Return the error page instead of the requested page.
            $content = $this->errorPage($this->translator->trans('Sorry, you are not allowed to perform this action.'), 403);
        }

        return $content;
    }
}
