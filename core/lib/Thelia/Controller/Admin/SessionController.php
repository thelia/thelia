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

use Thelia\Form\AdminLogin;
use Thelia\Core\Security\Authentication\AdminUsernamePasswordFormAuthenticator;
use Thelia\Model\AdminLog;
use Thelia\Core\Security\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Thelia\Tools\URL;
use Thelia\Tools\Redirect;
use Thelia\Core\Event\TheliaEvents;

class SessionController extends BaseAdminController
{
    public function showLoginAction()
    {
        return $this->render("login");
    }

    public function checkLogoutAction()
    {
        $this->dispatch(TheliaEvents::ADMIN_LOGOUT);

        $this->getSecurityContext()->clearAdminUser();

        // Go back to login page.
        return Redirect::exec(URL::absoluteUrl('/admin/login')); // FIXME - should be a parameter
    }

    public function checkLoginAction()
    {
        $adminLoginForm = new AdminLogin($this->getRequest());

        $request = $this->getRequest();

        $authenticator = new AdminUsernamePasswordFormAuthenticator($request, $adminLoginForm);

        try {
            $user = $authenticator->getAuthentifiedUser();

            // Success -> store user in security context
            $this->getSecurityContext()->setAdminUser($user);

            // Log authentication success
            AdminLog::append("Authentication successful", $request, $user);

            $this->dispatch(TheliaEvents::ADMIN_LOGIN);

            // Redirect to the success URL
            return Redirect::exec($adminLoginForm->getSuccessUrl());
         } catch (ValidatorException $ex) {

             // Validation problem
             $message = "Missing or invalid information. Please check your input.";
         } catch (AuthenticationException $ex) {

             // Log authentication failure
             AdminLog::append(sprintf("Authentication failure for username '%s'", $authenticator->getUsername()), $request);

             $message = "Login failed. Please check your username and password.";
         } catch (\Exception $ex) {

             // Log authentication failure
             AdminLog::append(sprintf("Undefined error: %s", $ex->getMessage()), $request);

             $message = "Unable to process your request. Please try again.";
         }

         // Store error information in the form
         $adminLoginForm->setError(true);
         $adminLoginForm->setErrorMessage($message);

         // Store the form name in session (see Form Smarty plugin to find usage of this parameter)
         $this->getParserContext()->setErrorForm($adminLoginForm);

          // Display the login form again
        return $this->render("login");
    }
}
