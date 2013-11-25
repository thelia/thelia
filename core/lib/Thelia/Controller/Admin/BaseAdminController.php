<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Controller\Admin;

use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Thelia\Controller\BaseController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Model\ConfigQuery;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Tools\URL;
use Thelia\Tools\Redirect;
use Thelia\Model\AdminLog;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Symfony\Component\Routing\Router;
use Thelia\Model\Admin;
use Thelia\Core\Security\Token\CookieTokenProvider;
use Thelia\Model\CurrencyQuery;
use Thelia\Core\Template\TemplateHelper;

class BaseAdminController extends BaseController
{
    const TEMPLATE_404 = "404";

    /**
     * Helper to append a message to the admin log.
     *
     * @param string $resource
     * @param string $action
     * @param string $message
     */
    public function adminLogAppend($resource, $action, $message)
    {
        AdminLog::append($resource, $action, $message, $this->getRequest(), $this->getSecurityContext()->getAdminUser());
    }

    /**
     * This method process the rendering of view called from an admin page
     *
     * @param  unknown  $template
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
     * @param mixed $message a message string, or an exception instance
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function errorPage($message, $status = 500)
    {
        if ($message instanceof \Exception) {
            $message = $this->getTranslator()->trans("Sorry, an error occured: %msg", array('%msg' => $message->getMessage()));
        }

        return $this->render('general_error',
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

         // Generate the proper response
         $response = new Response();

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
     * @param string    $action        the action that caused the error (category modification, variable creation, currency update, etc.)
     * @param BaseForm  $form          the form where the error occured, or null if no form was involved
     * @param string    $error_message the error message
     * @param Exception $exception     the exception or null if no exception
     */
    protected function setupFormErrorContext($action,  $error_message, BaseForm $form = null, \Exception $exception = null)
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
     * @return a ParserInterface instance parser
     */
    protected function getParser($template = null)
    {
        $parser = $this->container->get("thelia.parser");

        // Define the template that should be used
        $parser->setTemplateDefinition($template ?: TemplateHelper::getInstance()->getActiveAdminTemplate());

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
     * @throws \InvalidArgumentException When the router doesn't exist
     * @return string                    The generated URL
     *
     * @see \Thelia\Controller\BaseController::getRouteFromRouter()
     */
    protected function getRoute($routeId, $parameters = array(), $referenceType = Router::ABSOLUTE_URL)
    {
        return $this->getRouteFromRouter(
            'router.admin',
            $routeId,
            $parameters,
            $referenceType
        );
    }

    /**
     * Redirect to à route ID related URL
     *
     * @param unknown $routeId       the route ID, as found in Config/Resources/routing/admin.xml
     * @param unknown $urlParameters the URL parametrs, as a var/value pair array
     */
    public function redirectToRoute($routeId, array $urlParameters = array(), array $routeParameters = array())
    {
        $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute($routeId, $routeParameters), $urlParameters));
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
     * Return the current list order identifier for a given object name,
     * updating in using the current request.
     *
     * @param unknown $objectName           the object name (e.g. 'attribute', 'message')
     * @param unknown $requestParameterName the name of the request parameter that defines the list order
     * @param unknown $defaultListOrder     the default order to use, if none is defined
     * @param string  $updateSession        if true, the session will be updated with the current order.
     *
     * @return String the current liste order.
     */
    protected function getListOrderFromSession($objectName, $requestParameterName, $defaultListOrder, $updateSession = true)
    {
        $order = $defaultListOrder;

        $orderSessionIdentifier = sprintf("admin.%s.currentListOrder", $objectName);

        // Find the current order
        $order = $this->getRequest()->get(
                $requestParameterName,
                $this->getSession()->get($orderSessionIdentifier, $defaultListOrder)
        );

        if ($updateSession) $this->getSession()->set($orderSessionIdentifier, $order);
        return $order;
    }

    /**
     * Create the remember me cookie for the given user.
     */
    protected function createAdminRememberMeCookie(Admin $user)
    {
        $ctp = new CookieTokenProvider();

        $cookieName = ConfigQuery::read('admin_remember_me_cookie_name', 'armcn');
        $cookieExpiration = ConfigQuery::read('admin_remember_me_cookie_expiration', 2592000 /* 1 month */);

        $ctp->createCookie($user, $cookieName, $cookieExpiration);
    }

    /**
     * Get the rememberme key from the cookie.
     *
     * @return string hte key found, or null if no key was found.
     */
    protected function getRememberMeKeyFromCookie()
    {
       // Check if we can authenticate the user with a cookie-based token
        $cookieName = ConfigQuery::read('admin_remember_me_cookie_name', 'armcn');

        $ctp = new CookieTokenProvider();

        return $ctp->getKeyFromCookie($this->getRequest(), $cookieName);
    }

    /** Clear the remember me cookie.
     *
     */
    protected function clearRememberMeCookie()
    {
        $ctp = new CookieTokenProvider();

        $cookieName = ConfigQuery::read('admin_remember_me_cookie_name', 'armcn');

        $ctp->clearCookie($cookieName);
    }

    /**
     * Render the given template, and returns the result as an Http Response.
     *
     * @param $templateName the complete template name, with extension
     * @param  array                                      $args   the template arguments
     * @param  int                                        $status http code status
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function render($templateName, $args = array(), $status = 200)
    {
        return Response::create($this->renderRaw($templateName, $args), $status);
    }

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param $templateName the complete template name, with extension
     * @param array $args        the template arguments
     * @param null  $templateDir
     *
     * @return \Thelia\Core\HttpFoundation\Response
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
            $data = $this->getParser($templateDir)->render($templateName, $args);

            return $data;
        } catch (AuthenticationException $ex) {
            // User is not authenticated, and templates requires authentication -> redirect to login page
            // We user login_tpl as a path, not a template.
            Redirect::exec(URL::getInstance()->absoluteUrl($ex->getLoginTemplate()));
        } catch (AuthorizationException $ex) {
            // User is not allowed to perform the required action. Return the error page instead of the requested page.
            return $this->errorPage($this->getTranslator()->trans("Sorry, you are not allowed to perform this action."), 403);
        }
    }
}
