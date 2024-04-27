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

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Controller\BaseController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
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

    /**
     * The current router identifier. The default is router.admin. Modules may use
     * setCurrentRouter() method to pass their own router, and use the route related
     * methods of this class.
     */
    protected string $currentRouter = 'router.admin';

    /**
     * This method process the rendering of view called from an admin page.
     *
     * @param string $template the template name
     *
     * @return Response the response which contains the rendered view
     */
    public function processTemplateAction(string $template)
    {
        try {
            if (!empty($template)) {
                // If we have a view in the URL, render this view
                return $this->render($template);
            }
            if (null !== $view = $this->requestStack->getCurrentRequest()?->get('view')) {
                return $this->render($view);
            }
        } catch (\Exception $ex) {
            return $this->errorPage($ex->getMessage());
        }

        return $this->pageNotFound();
    }

    public function getControllerType(): string
    {
        return self::CONTROLLER_TYPE;
    }

    /**
     * Helper to append a message to the admin log.
     */
    protected function adminLogAppend(string $resource, string $action, string $message, string $resourceId = null): void
    {
        AdminLog::append(
            $resource,
            $action,
            $message,
            $this->requestStack->getCurrentRequest(),
            $this->securityContext->getAdminUser(),
            true,
            $resourceId
        );
    }

    /**
     * Return a 404 error.
     */
    protected function pageNotFound(): Response
    {
        return new Response($this->renderRaw(self::TEMPLATE_404), 404);
    }

    /**
     * Return a general error page.
     *
     * @param \Exception|string $message a message string, or an exception instance
     * @param int               $status  the HTTP status (default is 500)
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function errorPage($message, $status = 500)
    {
        if ($message instanceof \Exception) {
            $strMessage = $this->translator->trans(
                'Sorry, an error occured: %msg',
                ['%msg' => $message->getMessage()]
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
            $status
        );
    }

    /**
     * Check current admin user authorisations. An ADMIN role is assumed.
     *
     * @param mixed $resources a single resource or an array of resources
     * @param mixed $modules   a single module or an array of modules
     * @param mixed $accesses  a single access or an array of accesses
     *
     * @return mixed null if authorization is granted, or a Response object which contains the error page otherwise
     */
    protected function checkAuth($resources, $modules, $accesses)
    {
        $resources = \is_array($resources) ? $resources : [$resources];
        $modules = \is_array($modules) ? $modules : [$modules];
        $accesses = \is_array($accesses) ? $accesses : [$accesses];

        if ($this->securityContext->isGranted(['ADMIN'], $resources, $modules, $accesses)) {
            // Okay !
            return null;
        }

        // Log the problem
        $this->adminLogAppend(implode(',', $resources), implode(',', $accesses), 'User is not granted for resources %s with accesses %s', implode(', ', $resources));

        return $this->errorPage($this->translator->trans("Sorry, you're not allowed to perform this action"), 403);
    }

    /*
     * Create the standard message displayed to the user when the form cannot be validated.
     */
    protected function createStandardFormValidationErrorMessage(FormValidationException $exception)
    {
        return $this->translator->trans(
            'Please check your input: %error',
            [
                '%error' => $exception->getMessage(),
            ]
        );
    }

    /**
     * Setup the error context when an error occurs in a action method.
     *
     * @param string          $action        the action that caused the error (category modification, variable creation, currency update, etc.)
     * @param BaseForm        $form          the form where the error occured, or null if no form was involved
     * @param string          $error_message the error message
     * @param \Exception|null $exception     the exception or null if no exception
     */
    protected function setupFormErrorContext(string $action, string $error_message, BaseForm $form = null, \Exception $exception = null): void
    {
        if ($error_message !== false) {
            // Log the error message
            Tlog::getInstance()->error(
                $this->translator->trans(
                    'Error during %action process : %error. Exception was %exc',
                    [
                        '%action' => $action,
                        '%error' => $error_message,
                        '%exc' => $exception != null ? $exception->getMessage() : 'no exception',
                    ]
                )
            );

            if ($form !== null) {
                // Mark the form as errored
                $form->setErrorMessage($error_message);

                // Pass it to the parser context
                $this->getParserContext()->addForm($form);
            }

            // Pass the error message to the parser.
            $this->getParserContext()->setGeneralError($error_message);
        }
    }

    /**
     * @param TemplateDefinition $template the template to process
     *
     * @return ParserInterface instance parser
     */
    protected function getParser($template = null)
    {
        $parser = $this->parserResolver->getParser($template);
        // Define the template that should be used
        $parser->setTemplateDefinition(
            $parser->getTemplateDefinition() ?: $this->templateHelper->getActiveAdminTemplate(),
            $this->useFallbackTemplate
        );

        return $parser;
    }

    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     *
     * @return Response A Response instance
     */
    protected function forward(string $controller, array $path = [], array $query = [])
    {
        $path['_controller'] = $controller;
        $subRequest = $this->requestStack->getCurrentRequest()?->duplicate($query, null, $path);

        return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Get the current edition currency ID, checking if a change was requested in the current request.
     */
    protected function getCurrentEditionCurrency()
    {
        // Return the new language if a change is required.
        if (null !== $edit_currency_id = $this->requestStack->getCurrentRequest()?->get('edit_currency_id', null)) {
            if (null !== $edit_currency = CurrencyQuery::create()->findOneById($edit_currency_id)) {
                return $edit_currency;
            }
        }

        // Otherwise return the lang stored in session.
        return $this->getSession()->getAdminEditionCurrency();
    }

    /**
     * Get the current edition lang ID, checking if a change was requested in the current request.
     */
    protected function getCurrentEditionLang()
    {
        // Return the new language if a change is required.
        if (null !== $edit_language_id = $this->requestStack->getCurrentRequest()?->get('edit_language_id', null)) {
            if (null !== $edit_language = LangQuery::create()->findOneById($edit_language_id)) {
                return $edit_language;
            }
        }

        // Otherwise return the lang stored in session.
        return $this->getSession()->getAdminEditionLang();
    }

    /**
     * A simple helper to get the current edition locale.
     */
    protected function getCurrentEditionLocale()
    {
        return $this->getCurrentEditionLang()->getLocale();
    }

    /**
     * A simple helper to get the URL based on the language.
     *
     * @param string|null $locale the locale, or null to get the current one
     *
     * @return string|null the URL for the current language, or null if the "One domain for each lang" feature is disabled
     */
    protected function getUrlLanguage(string $locale = null)
    {
        // Check if the functionality is activated
        if (!ConfigQuery::isMultiDomainActivated()) {
            return null;
        }

        // If we don't have a locale value, use the locale value in the session
        if (!$locale) {
            $locale = $this->getCurrentEditionLocale();
        }

        return LangQuery::create()->findOneByLocale($locale)->getUrl();
    }

    /**
     * Return the current list order identifier for a given object name,
     * updating in using the current request.
     *
     * @param string      $objectName           the object name (e.g. 'attribute', 'message')
     * @param string|null $requestParameterName the name of the request parameter that defines the list order
     * @param string|null $defaultListOrder     the default order to use, if none is defined
     * @param bool        $updateSession        if true, the session will be updated with the current order
     *
     * @return string the current list order
     */
    protected function getListOrderFromSession(string $objectName, ?string $requestParameterName, ?string $defaultListOrder, bool $updateSession = true)
    {
        $orderSessionIdentifier = sprintf('admin.%s.currentListOrder', $objectName);

        if (null === $requestParameterName || null === $defaultListOrder) {
            return null;
        }

        // Find the current order
        $order = $this->requestStack->getCurrentRequest()?->get(
            $requestParameterName,
            $this->getSession()->get($orderSessionIdentifier, $defaultListOrder)
        );

        if ($updateSession) {
            $this->getSession()->set($orderSessionIdentifier, $order);
        }

        return $order;
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
        $response = $this->renderRaw($templateName, $args);

        if (!$response instanceof \Symfony\Component\HttpFoundation\Response) {
            $response = new Response($response, $status);
        }

        return $response;
    }

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param string $templateName the complete template name, with extension
     * @param array  $args         the template arguments
     * @param null   $templateDir
     *
     * @return string|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function renderRaw($templateName, $args = [], $templateDir = null)
    {
        $parser = $this->getParser($templateDir.'/'.$templateName);

        // Add the template standard extension
        $templateName .= '.'.$parser->getFileExtension();

        // Find the current edit language ID
        $edition_language = $this->getCurrentEditionLang();

        // Find the current edit currency ID
        $edition_currency = $this->getCurrentEditionCurrency();

        // Prepare common template variables
        $args = array_merge($args, [
            'edit_language_id' => $edition_language->getId(),
            'edit_language_locale' => $edition_language->getLocale(),
            'edit_currency_id' => $edition_currency->getId(),
        ]);

        // Update the current edition language & currency in session
        $this->getSession()
            ->setAdminEditionLang($edition_language)
            ->setAdminEditionCurrency($edition_currency)
        ;

        // Render the template.
        try {
            $content = $parser->render($templateName, $args);
        } catch (AuthenticationException $ex) {
            // User is not authenticated, and templates requires authentication -> redirect to login page
            // We user login_tpl as a path, not a template.
            $content = new RedirectResponse(URL::getInstance()->absoluteUrl($ex->getLoginTemplate()));
        } catch (AuthorizationException $ex) {
            // User is not allowed to perform the required action. Return the error page instead of the requested page.
            $content = $this->errorPage($this->translator->trans('Sorry, you are not allowed to perform this action.'), 403);
        }

        return $content;
    }
}
