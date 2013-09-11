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
use Symfony\Component\HttpFoundation\Response;
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

class BaseAdminController extends BaseController
{
    const TEMPLATE_404 = "404";

    /**
     * Helper to append a message to the admin log.
     *
     * @param unknown $message
     */
    public function adminLogAppend($message)
    {
        AdminLog::append($message, $this->getRequest(), $this->getSecurityContext()->getAdminUser());
    }

    /**
     * This method process the rendering of view called from an admin page
     *
     * @param  unknown  $template
     * @return Response the reponse which contains the rendered view
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
     * @return \Symfony\Component\HttpFoundation\Response
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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function errorPage($message)
    {
        if ($message instanceof \Exception) {
            $message = sprintf($this->getTranslator()->trans("Sorry, an error occured: %msg"), array('msg' => $message->getMessage()));
        }

        return $this->render('general_error', array(
                "error_message" => $message)
        );
    }

    /**
     * Check current admin user authorisations. An ADMIN role is assumed.
     *
     * @param mixed $permissions a single permission or an array of permissions.
     *
     * @return mixed null if authorization is granted, or a Response object which contains the error page otherwise
     *
     */
    protected function checkAuth($permissions)
    {
         $permArr = is_array($permissions) ? $permissions : array($permissions);

         if ($this->getSecurityContext()->isGranted(array("ADMIN"), $permArr)) {
             // Okay !
             return null;
         }

         // Log the problem
         $this->adminLogAppend("User is not granted for permissions %s", implode(", ", $permArr));

         // Generate the proper response
         $response = new Response();

         return $this->errorPage($this->getTranslator()->trans("Sorry, you're not allowed to perform this action"));
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
    protected function getParser()
    {
        $parser = $this->container->get("thelia.parser");

        // Define the template thant shoud be used
        $parser->setTemplate(ConfigQuery::read('base_admin_template', 'admin/default'));

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
    protected function getRoute($routeId, $parameters = array(), $referenceType = Router::ABSOLUTE_PATH)
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
    public function redirectToRoute($routeId, $urlParameters = array())
    {
        $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute($routeId), $urlParameters));
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
     * Render the given template, and returns the result as an Http Response.
     *
     * @param $templateName the complete template name, with extension
     * @param  array                                      $args the template arguments
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function render($templateName, $args = array())
    {
        $response = new Response();

        return $response->setContent($this->renderRaw($templateName, $args));
    }

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param $templateName the complete template name, with extension
     * @param  array                                      $args the template arguments
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderRaw($templateName, $args = array())
    {

        // Add the template standard extension
        $templateName .= '.html';

        $session = $this->getSession();

        // Find the current edit language ID
        $edition_language = $this->getCurrentEditionLang();

        // Prepare common template variables
        $args = array_merge($args, array(
            'locale'               => $session->getLang()->getLocale(),
            'lang_code'            => $session->getLang()->getCode(),
            'lang_id'              => $session->getLang()->getId(),

            'edit_language_id'     => $edition_language->getId(),
            'edit_language_locale' => $edition_language->getLocale(),

            'current_url'          => htmlspecialchars($this->getRequest()->getUri())
        ));

        // Update the current edition language in session
        $this->getSession()->setAdminEditionLang($edition_language);

        // Render the template.
        try {
            $data = $this->getParser()->render($templateName, $args);

            return $data;
        } catch (AuthenticationException $ex) {
            // User is not authenticated, and templates requires authentication -> redirect to login page
            // We user login_tpl as a path, not a template.
            Redirect::exec(URL::getInstance()->absoluteUrl($ex->getLoginTemplate()));
        } catch (AuthorizationException $ex) {
            // User is not allowed to perform the required action. Return the error page instead of the requested page.
            return $this->errorPage($this->getTranslator()->trans("Sorry, you are not allowed to perform this action."));
        }
    }
}
