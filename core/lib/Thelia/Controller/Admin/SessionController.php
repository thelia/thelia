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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Authentication\AdminUsernamePasswordFormAuthenticator;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Form\AdminLogin;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\AdminLog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

class SessionController extends BaseAdminController
{
    use \Thelia\Tools\RememberMeTrait;


    public function showLoginAction()
    {
        // Check if user is already authenticate
        if ($this->getSecurityContext()->hasAdminUser()) {
            // Redirect to the homepage
            return new RedirectResponse($this->retrieveUrlFromRouteId('admin.home.view'));
        }

        return $this->render("login");
    }

    public function checkLogoutAction()
    {
        $this->dispatch(TheliaEvents::ADMIN_LOGOUT);

        $this->getSecurityContext()->clearAdminUser();

        // Clear the remember me cookie, if any
        $this->clearRememberMeCookie($this->getRememberMeCookieName());

        // Go back to login page.
        return $this->generateRedirectFromRoute('admin.login');
    }

    public function checkLoginAction()
    {
        $request = $this->getRequest();

        $adminLoginForm = new AdminLogin($request);

        try {
            $form = $this->validateForm($adminLoginForm, "post");

            $authenticator = new AdminUsernamePasswordFormAuthenticator($request, $adminLoginForm);

            $user = $authenticator->getAuthentifiedUser();

            // Success -> store user in security context
            $this->getSecurityContext()->setAdminUser($user);

            // Log authentication success
            AdminLog::append("admin", "LOGIN", "Authentication successful", $request, $user, false);

            $this->applyUserLocale($user);

            /**
             * we have tou find a way to send cookie
             */
            if (intval($form->get('remember_me')->getData()) > 0) {
                // If a remember me field if present and set in the form, create
                // the cookie thant store "remember me" information
                $this->createRememberMeCookie(
                    $user,
                    $this->getRememberMeCookieName(),
                    $this->getRememberMeCookieExpiration()
                );
            }

            $this->dispatch(TheliaEvents::ADMIN_LOGIN);

            // Redirect to the success URL, passing the cookie if one exists.
            return $this->generateSuccessRedirect($adminLoginForm);
        } catch (FormValidationException $ex) {
            // Validation problem
             $message = $this->createStandardFormValidationErrorMessage($ex);
        } catch (AuthenticationException $ex) {
            // Log authentication failure
             AdminLog::append("admin", "LOGIN", sprintf("Authentication failure for username '%s'", $authenticator->getUsername()), $request);

            $message =  $this->getTranslator()->trans("Login failed. Please check your username and password.");
        } catch (\Exception $ex) {
            // Log authentication failure
             AdminLog::append("admin", "LOGIN", sprintf("Undefined error: %s", $ex->getMessage()), $request);

            $message = $this->getTranslator()->trans(
                "Unable to process your request. Please try again (%err).",
                array("%err" => $ex->getMessage())
            );
        }

        $this->setupFormErrorContext("Login process", $message, $adminLoginForm, $ex);

          // Display the login form again
        return $this->render("login");
    }

    /**
     * Save user locale preference in session.
     *
     * @param UserInterface $user
     */
    protected function applyUserLocale(UserInterface $user)
    {
        // Set the current language according to locale preference
        $locale = $user->getLocale();

        if (null === $lang = LangQuery::create()->findOneByLocale($locale)) {
            $lang = Lang::getDefaultLanguage();
        }

        $this->getSession()->setLang($lang);
    }

    protected function getRememberMeCookieName()
    {
        return ConfigQuery::read('admin_remember_me_cookie_name', 'armcn');
    }

    protected function getRememberMeCookieExpiration()
    {
        return ConfigQuery::read('admin_remember_me_cookie_expiration', 2592000 /* 1 month */);
    }
}
