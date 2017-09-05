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
use Thelia\Action\Administrator;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Authentication\AdminUsernamePasswordFormAuthenticator;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Exception\TheliaProcessException;
use Thelia\Form\AdminLogin;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Admin;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

class SessionController extends BaseAdminController
{
    use \Thelia\Tools\RememberMeTrait;

    const ADMIN_TOKEN_SESSION_VAR_NAME = 'thelia_admin_password_renew_token';

    protected function checkAdminLoggedIn()
    {
        // Check if user is already authenticate
        if ($this->getSecurityContext()->hasAdminUser()) {
            // Redirect to the homepage
            return new RedirectResponse($this->retrieveUrlFromRouteId('admin.home.view'));
        }

        return null;
    }

    protected function checkPasswordRecoveryEnabled()
    {
        // Check if user is already authenticate
        if (! boolval(ConfigQuery::read('enable_lost_admin_password_recovery', false))) {
            AdminLog::append(
                "admin",
                "ADMIN_CREATE_PASSWORD",
                "Lost password recovery function invoked",
                $this->getRequest()
            );

            // Redirect to the error page
            return $this->errorPage($this->getTranslator()->trans("The lost admin password recovery feature is disabled."), 403);
        }
    }

    public function showLoginAction()
    {
        if (null !== $response = $this->checkAdminLoggedIn()) {
            return $response;
        }

        return $this->render("login");
    }

    public function showLostPasswordAction()
    {
        if ((null !== $response = $this->checkPasswordRecoveryEnabled()) || (null !== $response = $this->checkAdminLoggedIn())) {
            return $response;
        }

        return $this->render("lost-password");
    }


    public function passwordCreateRequestAction()
    {
        if ((null !== $response = $this->checkPasswordRecoveryEnabled()) || (null !== $response = $this->checkAdminLoggedIn())) {
            return $response;
        }

        $adminLostPasswordForm = $this->createForm(AdminForm::ADMIN_LOST_PASSWORD);

        try {
            $form = $this->validateForm($adminLostPasswordForm, "post");

            $data = $form->getData();

            // Check if user exists
            if (null === $admin = AdminQuery::create()->findOneByEmail($data['username_or_email'])) {
                $admin = AdminQuery::create()->findOneByLogin($data['username_or_email']);
            }

            if (null === $admin) {
                throw new \Exception($this->getTranslator()->trans("Invalid username or email."));
            }

            $email = $admin->getEmail();

            if (empty($email)) {
                throw new \Exception($this->getTranslator()->trans("Sorry, no email defined for this administrator."));
            }

            $this->dispatch(TheliaEvents::ADMINISTRATOR_CREATEPASSWORD, new AdministratorEvent($admin));

            // Redirect to the success URL
            return $this->generateSuccessRedirect($adminLostPasswordForm);
        } catch (FormValidationException $ex) {
            // Validation problem
            $message = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Log failure
            AdminLog::append("admin", "ADMIN_LOST_PASSWORD", $ex->getMessage(), $this->getRequest());

            $message = $ex->getMessage();
        }

        $this->setupFormErrorContext("Admin password create request", $message, $adminLostPasswordForm, $ex);

        return $this->render("lost-password");
    }

    public function passwordCreateRequestSuccessAction()
    {
        if ((null !== $response = $this->checkPasswordRecoveryEnabled()) || (null !== $response = $this->checkAdminLoggedIn())) {
            return $response;
        }

        return $this->render("lost-password", [ 'create_request_success' => true ]);
    }

    public function displayCreateFormAction($token)
    {
        if ((null !== $response = $this->checkPasswordRecoveryEnabled()) || (null !== $response = $this->checkAdminLoggedIn())) {
            return $response;
        }

        // Check the token
        if (null == $admin = AdminQuery::create()->findOneByPasswordRenewToken($token)) {
            return $this->render(
                "lost-password",
                [ 'token_error' => true ]
            );
        }

        $this->getSession()->set(self::ADMIN_TOKEN_SESSION_VAR_NAME, $token);

        return $this->render("create-password");
    }

    public function passwordCreatedAction()
    {
        if ((null !== $response = $this->checkPasswordRecoveryEnabled()) || (null !== $response = $this->checkAdminLoggedIn())) {
            return $response;
        }

        $adminCreatePasswordForm = $this->createForm(AdminForm::ADMIN_CREATE_PASSWORD);

        try {
            $form = $this->validateForm($adminCreatePasswordForm, "post");

            $data = $form->getData();

            $token = $this->getSession()->get(self::ADMIN_TOKEN_SESSION_VAR_NAME);

            if (empty($token) || null === $admin = AdminQuery::create()->findOneByPasswordRenewToken($token)) {
                throw new \Exception($this->getTranslator()->trans("An invalid token was provided, your password cannot be changed"));
            }

            $event = new AdministratorUpdatePasswordEvent($admin);
            $event->setPassword($data['password']);

            $this->dispatch(TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD, $event);

            $this->getSession()->set(self::ADMIN_TOKEN_SESSION_VAR_NAME, null);

            return $this->generateSuccessRedirect($adminCreatePasswordForm);
        } catch (FormValidationException $ex) {
            // Validation problem
            $message = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Log authentication failure
            AdminLog::append("admin", "ADMIN_CREATE_PASSWORD", $ex->getMessage(), $this->getRequest());

            $message = $ex->getMessage();
        }

        $this->setupFormErrorContext("Login process", $message, $adminCreatePasswordForm, $ex);

        return $this->render("create-password");
    }

    public function passwordCreatedSuccessAction()
    {
        if ((null !== $response = $this->checkPasswordRecoveryEnabled()) || (null !== $response = $this->checkAdminLoggedIn())) {
            return $response;
        }

        return $this->render("create-password-success");
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

            /** @var Admin $user */
            $user = $authenticator->getAuthentifiedUser();

            // Success -> store user in security context
            $this->getSecurityContext()->setAdminUser($user);

            // Log authentication success
            AdminLog::append("admin", "LOGIN", "Authentication successful", $request, $user, false);

            $this->applyUserLocale($user);

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

            // Check if we have to ask the user to set its address email.
            // This is the case if Thelia has been updated from a pre 2.3.0 version
            if (false === strpos($user->getEmail(), '@')) {
                return $this->generateRedirectFromRoute('admin.set-email-address');
            }

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
