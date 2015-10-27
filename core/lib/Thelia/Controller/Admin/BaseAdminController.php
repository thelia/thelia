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

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Controller\BaseController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
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
    const CONTROLLER_TYPE = 'admin';

    const TEMPLATE_404 = "404";

    /**
     * The current router identifier. The default is router.admin. Modules may use
     * setCurrentRouter() method to pass their own router, and use the route related
     * methods of this class.
     */
    protected $currentRouter = "router.admin";

    /**
     * Helper to append a message to the admin log.
     *
     * @param string $resource
     * @param string $action
     * @param string $message
     */
    public function adminLogAppend($resource, $action, $message, $resourceId = null)
    {
        AdminLog::append(
            $resource,
            $action,
            $message,
            $this->getRequest(),
            $this->getSecurityContext()->getAdminUser(),
            true,
            $resourceId
        );
    }

    /**
     * This method process the rendering of view called from an admin page
     *
     * @param  string   $template the template name
     * @return Response the response which contains the rendered view
     */
    public function processTemplateAction($template)
    {
        try {
            if (! empty($template)) {
                // If we have a view in the URL, render this view
                return $this->render($template);
            } elseif (null != $view = $this->getRequest()->get('view')) {
                return $this->render($view);
            }
        } catch (\Exception $ex) {
            return $this->errorPage($ex->getMessage());
        }

        return $this->pageNotFound();
    }

    /**
     * @return string
     */
    public function getControllerType()
    {
        return self::CONTROLLER_TYPE;
    }

    /**
     * Return a 404 error
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function pageNotFound()
    {
        return new Response($this->renderRaw(self::TEMPLATE_404), 404);
    }

    /**
     * Return a general error page
     *
     * @param \Exception|string $message a message string, or an exception instance
     * @param int    $status  the HTTP status (default is 500)
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function errorPage($message, $status = 500)
    {
        if ($message instanceof \Exception) {
            $strMessage = $this->getTranslator()->trans(
                "Sorry, an error occured: %msg",
                [ '%msg' => $message->getMessage() ]
            );

            Tlog::getInstance()->addError($strMessage.": ".$message->getTraceAsString());

            $message = $strMessage;
        } else {
            Tlog::getInstance()->addError($message);
        }

        return $this->render(
            'general_error',
            array(
                "error_message" => $message
            ),
            $status
        );
    }

    /**
     * Check current admin user authorisations. An ADMIN role is assumed.
     *
     * @param mixed $resources a single resource or an array of resources.
     * @param mixed $modules   a single module or an array of modules.
     * @param mixed $accesses  a single access or an array of accesses.
     *
     * @return mixed null if authorization is granted, or a Response object which contains the error page otherwise
     */
    protected function checkAuth($resources, $modules, $accesses)
    {
        $resources = is_array($resources) ? $resources : array($resources);
        $modules = is_array($modules) ? $modules : array($modules);
        $accesses = is_array($accesses) ? $accesses : array($accesses);

        if ($this->getSecurityContext()->isGranted(array("ADMIN"), $resources, $modules, $accesses)) {
            // Okay !
             return null;
        }

         // Log the problem
         $this->adminLogAppend(implode(",", $resources), implode(",", $accesses), "User is not granted for resources %s with accesses %s", implode(", ", $resources), implode(", ", $accesses));

        return $this->errorPage($this->getTranslator()->trans("Sorry, you're not allowed to perform this action"), 403);
    }

    /*
     * Create the standard message displayed to the user when the form cannot be validated.
     */
    protected function createStandardFormValidationErrorMessage(FormValidationException $exception)
    {
        return $this->getTranslator()->trans(
            "Please check your input: %error",
            array(
                '%error' => $exception->getMessage()
            )
        );
    }

    /**
     * Setup the error context when an error occurs in a action method.
     *
     * @param string     $action        the action that caused the error (category modification, variable creation, currency update, etc.)
     * @param BaseForm   $form          the form where the error occured, or null if no form was involved
     * @param string     $error_message the error message
     * @param \Exception $exception     the exception or null if no exception
     */
    protected function setupFormErrorContext($action, $error_message, BaseForm $form = null, \Exception $exception = null)
    {
        if ($error_message !== false) {
            // Log the error message
            Tlog::getInstance()->error(
                $this->getTranslator()->trans(
                    "Error during %action process : %error. Exception was %exc",
                    array(
                        '%action' => $action,
                        '%error'  => $error_message,
                        '%exc'    => $exception != null ? $exception->getMessage() : 'no exception'
                    )
                )
            );

            if ($form != null) {
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
        $parser = $this->container->get("thelia.parser");

        // Define the template that should be used
        $parser->setTemplateDefinition(
            $template ?: $this->getTemplateHelper()->getActiveAdminTemplate(),
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
    protected function forward($controller, array $path = array(), array $query = array())
    {
        $path['_controller'] = $controller;
        $subRequest = $this->container->get('request')->duplicate($query, null, $path);

        return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Get the current edition currency ID, checking if a change was requested in the current request.
     */
    protected function getCurrentEditionCurrency()
    {
        // Return the new language if a change is required.
        if (null !== $edit_currency_id = $this->getRequest()->get('edit_currency_id', null)) {
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
        if (null !== $edit_language_id = $this->getRequest()->get('edit_language_id', null)) {
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
     * @param  string      $locale the locale, or null to get the current one
     * @return null|string the URL for the current language, or null if the "One domain for each lang" feature is disabled.
     */
    protected function getUrlLanguage($locale = null)
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
     * @param string $objectName           the object name (e.g. 'attribute', 'message')
     * @param string $requestParameterName the name of the request parameter that defines the list order
     * @param string $defaultListOrder     the default order to use, if none is defined
     * @param bool   $updateSession        if true, the session will be updated with the current order.
     *
     * @return String the current list order.
     */
    protected function getListOrderFromSession($objectName, $requestParameterName, $defaultListOrder, $updateSession = true)
    {
        $orderSessionIdentifier = sprintf("admin.%s.currentListOrder", $objectName);

        // Find the current order
        $order = $this->getRequest()->get(
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
     * @param  string                               $templateName the complete template name, with extension
     * @param  array                                $args         the template arguments
     * @param  int                                  $status       http code status
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function render($templateName, $args = array(), $status = 200)
    {
        $response = $this->renderRaw($templateName, $args);

        if (!$response instanceof \Symfony\Component\HttpFoundation\Response) {
            $response = Response::create($response, $status);
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
    protected function renderRaw($templateName, $args = array(), $templateDir = null)
    {
        // Add the template standard extension
        $templateName .= '.html';

        $session = $this->getSession();

        // Find the current edit language ID
        $edition_language = $this->getCurrentEditionLang();

        // Find the current edit currency ID
        $edition_currency = $this->getCurrentEditionCurrency();

        // Prepare common template variables
        $args = array_merge($args, array(
            'locale'               => $session->getLang()->getLocale(),
            'lang_code'            => $session->getLang()->getCode(),
            'lang_id'              => $session->getLang()->getId(),

            'edit_language_id'     => $edition_language->getId(),
            'edit_language_locale' => $edition_language->getLocale(),

            'edit_currency_id'     => $edition_currency->getId(),

            'current_url'          => $this->getRequest()->getUri()
        ));

        // Update the current edition language & currency in session
        $this->getSession()
            ->setAdminEditionLang($edition_language)
            ->setAdminEditionCurrency($edition_currency)
        ;

        // Render the template.
        try {
            $content = $this->getParser($templateDir)->render($templateName, $args);
        } catch (AuthenticationException $ex) {
            // User is not authenticated, and templates requires authentication -> redirect to login page
            // We user login_tpl as a path, not a template.
            $content = RedirectResponse::create(URL::getInstance()->absoluteUrl($ex->getLoginTemplate()));
        } catch (AuthorizationException $ex) {
            // User is not allowed to perform the required action. Return the error page instead of the requested page.
            $content = $this->errorPage($this->getTranslator()->trans("Sorry, you are not allowed to perform this action."), 403);
        }

        return $content;
    }
}
