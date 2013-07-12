<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                          */
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

namespace Thelia\Admin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Form\AdminLogin;
use Thelia\Core\Security\Token\UsernamePasswordToken;
use Thelia\Core\Security\Authentication\UsernamePasswordAuthenticator;
use Thelia\Core\Security\Encoder\PasswordPhpCompatEncoder;
use Thelia\Core\Security\Token\TokenInterface;
use Thelia\Core\Security\Authentication\AdminUsernamePasswordFormAuthenticator;
use Thelia\Model\AdminLog;
use Thelia\Core\Security\Exception\AuthenticationException;

class SessionController extends BaseAdminController {

	public function showLoginAction()
	{
		$form = $this->getLoginForm();

		return $this->render("login.html", array(
				"form" => $form->createView()
		));
	}

	public function checkLogoutAction()
	{
		$this->getSecurityContext()->clear();

		// Go back to login page.
		return $this->redirect($this->generateUrl('admin/login'));
	}

    public function checkLoginAction()
    {
		$form = $this->getLoginForm();

    	$request = $this->getRequest();

    	$authenticator = new AdminUsernamePasswordFormAuthenticator($request, $form);

    	try {
    		$user = $authenticator->getAuthentifiedUser();

    		// Success -> store user in security context
    		$this->getSecurityContext()->setUser($user);

    		// Log authentication success
    		AdminLog::append("Authentication successufull", $request, $user);

    		// Redirect to home page - FIXME: requested URL, if we come from another page ?
    		return $this->redirect($this->generateUrl('admin'));
     	}
     	catch (AuthenticationException $ex) {

     		// Log authentication failure
     		AdminLog::append(sprintf("Authentication failure for username '%s'", $authenticator->getUsername()), $request);

     		$message = "Login failed. Please check your username and password.";
     	}

     	// Display the login form again
     	return $this->render("login.html", array(
			"form" => $authenticator->getLoginForm()->createView(),
     		"message" => $message
     	));
    }

    protected function getLoginForm()
    {
        $adminLogin = new AdminLogin($this->getRequest());

        return $adminLogin->getForm();
    }
}