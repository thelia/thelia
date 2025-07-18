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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Authentication\AdminUsernamePasswordFormAuthenticator;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Form\AdminLogin;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Admin;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Service\Model\LangService;
use Thelia\Tools\RememberMeTrait;
use Thelia\Tools\URL;

class SessionController extends BaseAdminController
{
    use RememberMeTrait;

    public const ADMIN_TOKEN_SESSION_VAR_NAME = 'thelia_admin_password_renew_token';

    public function __construct(
        private readonly LangService $langService,
    ) {
    }

    protected function checkAdminLoggedIn(): ?RedirectResponse
    {
        // Check if user is already authenticate
        if ($this->getSecurityContext()->hasAdminUser()) {
            // Redirect to the homepage
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin'));
        }

        return null;
    }

    protected function checkPasswordRecoveryEnabled(): ?Response
    {
        // Check if user is already authenticate
        if (!ConfigQuery::read('enable_lost_admin_password_recovery', false)) {
            AdminLog::append(
                'admin',
                'ADMIN_CREATE_PASSWORD',
                'Lost password recovery function invoked',
                $this->getRequest(),
            );

            // Redirect to the error page
            return $this->errorPage($this->getTranslator()->trans('The lost admin password recovery feature is disabled.'), 403);
        }

        return null;
    }

    public function showLoginAction(): RedirectResponse|Response
    {
        if (($response = $this->checkAdminLoggedIn()) instanceof RedirectResponse) {
            return $response;
        }

        return $this->render('login');
    }

    public function showLostPasswordAction(): Response|RedirectResponse
    {
        if ((($response = $this->checkPasswordRecoveryEnabled()) instanceof Response) || (($response = $this->checkAdminLoggedIn()) instanceof RedirectResponse)) {
            return $response;
        }

        return $this->render('lost-password');
    }

    public function passwordCreateRequestAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        if ((($response = $this->checkPasswordRecoveryEnabled()) instanceof Response) || (($response = $this->checkAdminLoggedIn()) instanceof RedirectResponse)) {
            return $response;
        }

        $adminLostPasswordForm = $this->createForm(AdminForm::ADMIN_LOST_PASSWORD);

        try {
            $form = $this->validateForm($adminLostPasswordForm, 'post');

            $data = $form->getData();

            // Check if user exists
            if (null === $admin = AdminQuery::create()->findOneByEmail($data['username_or_email'])) {
                $admin = AdminQuery::create()->findOneByLogin($data['username_or_email']);
            }

            if (null === $admin) {
                throw new \Exception($this->getTranslator()->trans('Invalid username or email.'));
            }

            $email = $admin->getEmail();

            if (empty($email)) {
                throw new \Exception($this->getTranslator()->trans('Sorry, no email defined for this administrator.'));
            }

            $eventDispatcher->dispatch(new AdministratorEvent($admin), TheliaEvents::ADMINISTRATOR_CREATEPASSWORD);

            // Redirect to the success URL
            return $this->generateSuccessRedirect($adminLostPasswordForm);
        } catch (FormValidationException $ex) {
            // Validation problem
            $message = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Log failure
            AdminLog::append('admin', 'ADMIN_LOST_PASSWORD', $ex->getMessage(), $this->getRequest());

            $message = $ex->getMessage();
        }

        $this->setupFormErrorContext('Admin password create request', $message, $adminLostPasswordForm, $ex);

        return $this->render('lost-password');
    }

    public function passwordCreateRequestSuccessAction(): Response|RedirectResponse
    {
        if ((($response = $this->checkPasswordRecoveryEnabled()) instanceof Response) || (($response = $this->checkAdminLoggedIn()) instanceof RedirectResponse)) {
            return $response;
        }

        return $this->render('lost-password', ['create_request_success' => true]);
    }

    public function displayCreateFormAction($token): Response|RedirectResponse
    {
        if ((($response = $this->checkPasswordRecoveryEnabled()) instanceof Response) || (($response = $this->checkAdminLoggedIn()) instanceof RedirectResponse)) {
            return $response;
        }

        // Check the token
        if (null === $admin = AdminQuery::create()->findOneByPasswordRenewToken($token)) {
            return $this->render(
                'lost-password',
                ['token_error' => true],
            );
        }

        $this->getSession()->set(self::ADMIN_TOKEN_SESSION_VAR_NAME, $token);

        return $this->render('create-password');
    }

    public function passwordCreatedAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        if ((($response = $this->checkPasswordRecoveryEnabled()) instanceof Response) || (($response = $this->checkAdminLoggedIn()) instanceof RedirectResponse)) {
            return $response;
        }

        $adminCreatePasswordForm = $this->createForm(AdminForm::ADMIN_CREATE_PASSWORD);

        try {
            $form = $this->validateForm($adminCreatePasswordForm, 'post');

            $data = $form->getData();

            $token = $this->getSession()->get(self::ADMIN_TOKEN_SESSION_VAR_NAME);

            if (empty($token) || null === $admin = AdminQuery::create()->findOneByPasswordRenewToken($token)) {
                throw new \Exception($this->getTranslator()->trans('An invalid token was provided, your password cannot be changed'));
            }

            $event = new AdministratorUpdatePasswordEvent($admin);
            $event->setPassword($data['password']);

            $eventDispatcher->dispatch($event, TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD);

            $this->getSession()->set(self::ADMIN_TOKEN_SESSION_VAR_NAME, null);

            return $this->generateSuccessRedirect($adminCreatePasswordForm);
        } catch (FormValidationException $ex) {
            // Validation problem
            $message = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Log authentication failure
            AdminLog::append('admin', 'ADMIN_CREATE_PASSWORD', $ex->getMessage(), $this->getRequest());

            $message = $ex->getMessage();
        }

        $this->setupFormErrorContext('Login process', $message, $adminCreatePasswordForm, $ex);

        return $this->render('create-password');
    }

    public function passwordCreatedSuccessAction(): Response|RedirectResponse
    {
        if ((($response = $this->checkPasswordRecoveryEnabled()) instanceof Response) || (($response = $this->checkAdminLoggedIn()) instanceof RedirectResponse)) {
            return $response;
        }

        return $this->render('create-password-success');
    }

    public function checkLogoutAction(EventDispatcherInterface $eventDispatcher): RedirectResponse
    {
        $eventDispatcher->dispatch(new DefaultActionEvent(), TheliaEvents::ADMIN_LOGOUT);

        $this->getSecurityContext()->clearAdminUser();

        // Clear the remember me cookie, if any
        $this->clearRememberMeCookie($this->getRememberMeCookieName());

        // Go back to login page.
        return $this->generateRedirectFromRoute('admin.login');
    }

    public function checkLoginAction(EventDispatcherInterface $eventDispatcher): RedirectResponse|Response|null
    {
        $request = $this->getRequest();

        /** @var AdminLogin $adminLoginForm */
        $adminLoginForm = $this->createForm('thelia.admin.login');

        $authenticator = null;

        try {
            $form = $this->validateForm($adminLoginForm, 'post');

            $authenticator = new AdminUsernamePasswordFormAuthenticator($request, $adminLoginForm);

            /** @var Admin $user */
            $user = $authenticator->getAuthentifiedUser();

            // Success -> store user in security context
            $this->getSecurityContext()->setAdminUser($user);

            // Log authentication success
            AdminLog::append('admin', 'LOGIN', 'Authentication successful', $request, $user, false);

            $this->applyUserLocale($user);

            if ((int) $form->get('remember_me')->getData() > 0) {
                // If a remember me field if present and set in the form, create
                // the cookie thant store "remember me" information
                $this->createRememberMeCookie(
                    $user,
                    $this->getRememberMeCookieName(),
                    $this->getRememberMeCookieExpiration(),
                );
            }

            $eventDispatcher->dispatch(new DefaultActionEvent(), TheliaEvents::ADMIN_LOGIN);

            // Check if we have to ask the user to set its address email.
            // This is the case if Thelia has been updated from a pre 2.3.0 version
            if (!str_contains((string) $user->getEmail(), '@')) {
                return $this->generateRedirectFromRoute('admin.set-email-address');
            }

            // Redirect to the success URL, passing the cookie if one exists.
            return $this->generateSuccessRedirect($adminLoginForm);
        } catch (FormValidationException $ex) {
            // Validation problem
            $message = $this->createStandardFormValidationErrorMessage($ex);
        } catch (AuthenticationException $ex) {
            $username = $authenticator instanceof AdminUsernamePasswordFormAuthenticator ? $authenticator->getUsername() : 'unknown';
            // Log authentication failure
            AdminLog::append('admin', 'LOGIN', \sprintf("Authentication failure for username '%s'", $username), $request);

            $message = $this->getTranslator()->trans('Login failed. Please check your username and password.');
        } catch (\Exception $ex) {
            // Log authentication failure
            AdminLog::append('admin', 'LOGIN', \sprintf('Undefined error: %s', $ex->getMessage()), $request);

            $message = $this->getTranslator()->trans(
                'Unable to process your request. Please try again (%err).',
                ['%err' => $ex->getMessage()],
            );
        }

        $this->setupFormErrorContext('Login process', $message, $adminLoginForm, $ex);

        // Display the login form again
        return $this->render('login');
    }

    /**
     * Save user locale preference in session.
     */
    protected function applyUserLocale(UserInterface $user): void
    {
        // Set the current language according to locale preference
        $locale = $user->getLocale();

        if (null === $lang = LangQuery::create()->filterByActive(true)->findOneByLocale($locale)) {
            $lang = Lang::getDefaultLanguage();
        }

        $this->langService->setLang($lang);
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
