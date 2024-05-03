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

namespace Front\Controller;

use Front\Front;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\LostPasswordEvent;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Authentication\CustomerUsernamePasswordFormAuthenticator;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\CustomerNotConfirmedException;
use Thelia\Core\Security\Exception\UsernameNotFoundException;
use Thelia\Core\Security\Exception\WrongPasswordException;
use Thelia\Form\CustomerLogin;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\Newsletter;
use Thelia\Model\NewsletterQuery;
use Thelia\Tools\RememberMeTrait;
use Thelia\Tools\URL;

/**
 * Class CustomerController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerController extends BaseFrontController
{
    use RememberMeTrait;

    /**
     * Display the register template if no customer logged.
     */
    public function viewLoginAction()
    {
        if ($this->getSecurityContext()->hasCustomerUser()) {
            // Redirect to home page
            return $this->generateRedirect(URL::getInstance()->getIndexPage());
        }

        return $this->render('login');
    }

    /**
     * Display the register template if no customer logged.
     */
    public function viewRegisterAction()
    {
        if ($this->getSecurityContext()->hasCustomerUser()) {
            // Redirect to home page
            return $this->generateRedirect(URL::getInstance()->getIndexPage());
        }

        return $this->render('register');
    }

    public function newPasswordAction(EventDispatcherInterface $eventDispatcher)
    {
        $passwordLost = $this->createForm(FrontForm::CUSTOMER_LOST_PASSWORD);

        if (!$this->getSecurityContext()->hasCustomerUser()) {
            try {
                $form = $this->validateForm($passwordLost);

                $event = new LostPasswordEvent($form->get('email')->getData());

                $eventDispatcher->dispatch($event, TheliaEvents::LOST_PASSWORD);

                return $this->generateSuccessRedirect($passwordLost);
            } catch (FormValidationException $e) {
                $message = $this->getTranslator()->trans(
                    'Please check your input: %s',
                    [
                        '%s' => $e->getMessage(),
                    ],
                    Front::MESSAGE_DOMAIN
                );
            } catch (\Exception $e) {
                $message = $this->getTranslator()->trans(
                    'Sorry, an error occurred: %s',
                    [
                        '%s' => $e->getMessage(),
                    ],
                    Front::MESSAGE_DOMAIN
                );
            }

            if ($message !== false) {
                Tlog::getInstance()->error(
                    sprintf(
                        'Error during customer creation process : %s. Exception was %s',
                        $message,
                        $e->getMessage()
                    )
                );
            }
        } else {
            $message = $this->getTranslator()->trans(
                "You're currently logged in. Please log out before requesting a new password.",
                [],
                Front::MESSAGE_DOMAIN
            );
        }

        $passwordLost->setErrorMessage($message);

        $this->getParserContext()
            ->addForm($passwordLost)
            ->setGeneralError($message)
        ;

        // Redirect to error URL if defined
        if ($passwordLost->hasErrorUrl()) {
            return $this->generateErrorRedirect($passwordLost);
        }
    }

    public function newPasswordSentAction(): void
    {
        $this->getParser()->assign('password_sent', true);
    }

    /**
     * Create a new customer.
     * On success, redirect to success_url if exists, otherwise, display the same view again.
     */
    public function createAction(EventDispatcherInterface $eventDispatcher)
    {
        if (!$this->getSecurityContext()->hasCustomerUser()) {
            $customerCreation = $this->createForm(FrontForm::CUSTOMER_CREATE);

            try {
                $form = $this->validateForm($customerCreation, 'post');

                $customerCreateEvent = $this->createEventInstance($form->getData());

                $eventDispatcher->dispatch($customerCreateEvent, TheliaEvents::CUSTOMER_CREATEACCOUNT);

                $newCustomer = $customerCreateEvent->getCustomer();

                // Newsletter
                if (true === $form->get('newsletter')->getData()) {
                    $newsletterEmail = $newCustomer->getEmail();
                    $nlEvent = new NewsletterEvent(
                        $newsletterEmail,
                        $this->getRequest()->getSession()->getLang()->getLocale()
                    );
                    $nlEvent->setFirstname($newCustomer->getFirstname());
                    $nlEvent->setLastname($newCustomer->getLastname());

                    // Security : Check if this new Email address already exist
                    if (null !== $newsletter = NewsletterQuery::create()->findOneByEmail($newsletterEmail)) {
                        $nlEvent->setId($newsletter->getId());
                        $eventDispatcher->dispatch($nlEvent, TheliaEvents::NEWSLETTER_UPDATE);
                    } else {
                        $eventDispatcher->dispatch($nlEvent, TheliaEvents::NEWSLETTER_SUBSCRIBE);
                    }
                }

                if (ConfigQuery::isCustomerEmailConfirmationEnable() && !$newCustomer->getEnable()) {
                    $response = $this->generateRedirectFromRoute('customer.login.view', ['confirmation_sent' => 1]);
                } else {
                    $this->processLogin($eventDispatcher, $customerCreateEvent->getCustomer());

                    $cart = $this->getSession()->getSessionCart($eventDispatcher);
                    if ($cart->getCartItems()->count() > 0) {
                        $response = $this->generateRedirectFromRoute('cart.view');
                    } else {
                        $response = $this->generateSuccessRedirect($customerCreation);
                    }
                }

                return $response;
            } catch (FormValidationException $e) {
                $message = $this->getTranslator()->trans(
                    'Please check your input: %s',
                    [
                        '%s' => $e->getMessage(),
                    ],
                    Front::MESSAGE_DOMAIN
                );
            } catch (\Exception $e) {
                $message = $this->getTranslator()->trans(
                    'Sorry, an error occured: %s',
                    [
                        '%s' => $e->getMessage(),
                    ],
                    Front::MESSAGE_DOMAIN
                );
            }

            Tlog::getInstance()->error(
                sprintf(
                    'Error during customer creation process : %s. Exception was %s',
                    $message,
                    $e->getMessage()
                )
            );

            $customerCreation->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($customerCreation)
                ->setGeneralError($message)
            ;

            // Redirect to error URL if defined
            if ($customerCreation->hasErrorUrl()) {
                return $this->generateErrorRedirect($customerCreation);
            }
        }
    }

    /**
     * Prepare customer data update.
     */
    public function viewAction(): void
    {
        $this->checkAuth();

        /** @var Customer $customer */
        $customer = $this->getSecurityContext()->getCustomerUser();
        $newsletter = NewsletterQuery::create()->findOneByEmail($customer->getEmail());
        $data = [
            'id' => $customer->getId(),
            'title' => $customer->getTitleId(),
            'firstname' => $customer->getFirstName(),
            'lastname' => $customer->getLastName(),
            'email' => $customer->getEmail(),
            'email_confirm' => $customer->getEmail(),
            'lang_id' => $customer->getLangId(),
            'newsletter' => $newsletter instanceof Newsletter ? !$newsletter->getUnsubscribed() : false,
        ];

        $customerProfileUpdateForm = $this->createForm(FrontForm::CUSTOMER_PROFILE_UPDATE, FormType::class, $data);

        // Pass it to the parser
        $this->getParserContext()->addForm($customerProfileUpdateForm);
    }

    public function updatePasswordAction(EventDispatcherInterface $eventDispatcher)
    {
        if ($this->getSecurityContext()->hasCustomerUser()) {
            $customerPasswordUpdateForm = $this->createForm(FrontForm::CUSTOMER_PASSWORD_UPDATE);

            try {
                /** @var Customer $customer */
                $customer = $this->getSecurityContext()->getCustomerUser();

                $form = $this->validateForm($customerPasswordUpdateForm, 'post');

                $customerChangeEvent = $this->createEventInstance($form->getData());
                $customerChangeEvent->setCustomer($customer);
                $eventDispatcher->dispatch($customerChangeEvent, TheliaEvents::CUSTOMER_UPDATEPROFILE);

                return $this->generateSuccessRedirect($customerPasswordUpdateForm);
            } catch (FormValidationException $e) {
                $message = $this->getTranslator()->trans(
                    'Please check your input: %s',
                    [
                        '%s' => $e->getMessage(),
                    ],
                    Front::MESSAGE_DOMAIN
                );
            } catch (\Exception $e) {
                $message = $this->getTranslator()->trans(
                    'Sorry, an error occured: %s',
                    [
                        '%s' => $e->getMessage(),
                    ],
                    Front::MESSAGE_DOMAIN
                );
            }

            Tlog::getInstance()->error(
                sprintf(
                    'Error during customer password modification process : %s.',
                    $message
                )
            );

            $customerPasswordUpdateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($customerPasswordUpdateForm)
                ->setGeneralError($message)
            ;

            // Redirect to error URL if defined
            if ($customerPasswordUpdateForm->hasErrorUrl()) {
                return $this->generateErrorRedirect($customerPasswordUpdateForm);
            }
        }
    }

    public function updateAction(EventDispatcherInterface $eventDispatcher)
    {
        if ($this->getSecurityContext()->hasCustomerUser()) {
            $customerProfileUpdateForm = $this->createForm(FrontForm::CUSTOMER_PROFILE_UPDATE);

            try {
                /** @var Customer $customer */
                $customer = $this->getSecurityContext()->getCustomerUser();
                $newsletterOldEmail = $customer->getEmail();

                $form = $this->validateForm($customerProfileUpdateForm, 'post');

                $customerChangeEvent = $this->createEventInstance($form->getData());
                $customerChangeEvent->setCustomer($customer);

                $customerChangeEvent->setEmailUpdateAllowed(
                    ((int) ConfigQuery::read('customer_change_email', 0)) ? true : false
                );

                $eventDispatcher->dispatch($customerChangeEvent, TheliaEvents::CUSTOMER_UPDATEPROFILE);

                $updatedCustomer = $customerChangeEvent->getCustomer();

                // Newsletter
                if (true === $form->get('newsletter')->getData()) {
                    $nlEvent = new NewsletterEvent(
                        $updatedCustomer->getEmail(),
                        $this->getRequest()->getSession()->getLang()->getLocale()
                    );
                    $nlEvent->setFirstname($updatedCustomer->getFirstname());
                    $nlEvent->setLastname($updatedCustomer->getLastname());

                    if (null !== $newsletter = NewsletterQuery::create()->findOneByEmail($newsletterOldEmail)) {
                        $nlEvent->setId($newsletter->getId());
                        $eventDispatcher->dispatch($nlEvent, TheliaEvents::NEWSLETTER_UPDATE);
                    } else {
                        $eventDispatcher->dispatch($nlEvent, TheliaEvents::NEWSLETTER_SUBSCRIBE);
                    }
                } else {
                    if (null !== $newsletter = NewsletterQuery::create()->findOneByEmail($newsletterOldEmail)) {
                        $nlEvent = new NewsletterEvent(
                            $updatedCustomer->getEmail(),
                            $this->getRequest()->getSession()->getLang()->getLocale()
                        );
                        $nlEvent->setId($newsletter->getId());
                        $eventDispatcher->dispatch($nlEvent, TheliaEvents::NEWSLETTER_UNSUBSCRIBE);
                    }
                }

                $this->processLogin($eventDispatcher, $updatedCustomer);

                return $this->generateSuccessRedirect($customerProfileUpdateForm);
            } catch (FormValidationException $e) {
                $message = $this->getTranslator()->trans(
                    'Please check your input: %s',
                    [
                        '%s' => $e->getMessage(),
                    ],
                    Front::MESSAGE_DOMAIN
                );
            } catch (\Exception $e) {
                $message = $this->getTranslator()->trans(
                    'Sorry, an error occured: %s',
                    [
                        '%s' => $e->getMessage(),
                    ],
                    Front::MESSAGE_DOMAIN
                );
            }

            Tlog::getInstance()->error(sprintf('Error during customer modification process : %s.', $message));

            $customerProfileUpdateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($customerProfileUpdateForm)
                ->setGeneralError($message)
            ;

            // Redirect to error URL if defined
            if ($customerProfileUpdateForm->hasErrorUrl()) {
                return $this->generateErrorRedirect($customerProfileUpdateForm);
            }
        }
    }

    /**
     * Perform user login. On a successful login, the user is redirected to the URL
     * found in the success_url form parameter, or / if none was found.
     *
     * If login is not successfull, the same view is displayed again.
     */
    public function loginAction(EventDispatcherInterface $eventDispatcher)
    {
        if (!$this->getSecurityContext()->hasCustomerUser()) {
            $request = $this->getRequest();
            $customerLoginForm = $this->createForm(CustomerLogin::class);

            try {
                $form = $this->validateForm($customerLoginForm, 'post');

                // If User is a new customer
                if ($form->get('account')->getData() == 0 && $form->get('email')->getErrors()->count() == 0) {
                    return $this->generateRedirectFromRoute(
                        'customer.create.process',
                        ['email' => $form->get('email')->getData()]
                    );
                }
                try {
                    $authenticator = new CustomerUsernamePasswordFormAuthenticator($request, $customerLoginForm);

                    /** @var Customer $customer */
                    $customer = $authenticator->getAuthentifiedUser();

                    $this->processLogin($eventDispatcher, $customer);

                    if ((int) $form->get('remember_me')->getData() > 0) {
                        // If a remember me field if present and set in the form, create
                        // the cookie thant store "remember me" information
                        $this->createRememberMeCookie(
                            $customer,
                            $this->getRememberMeCookieName(),
                            $this->getRememberMeCookieExpiration()
                        );
                    }

                    return $this->generateSuccessRedirect($customerLoginForm);
                } catch (UsernameNotFoundException $e) {
                    $message = $this->getTranslator()->trans(
                        'Wrong email or password. Please try again',
                        [],
                        Front::MESSAGE_DOMAIN
                    );
                } catch (WrongPasswordException $e) {
                    $message = $this->getTranslator()->trans(
                        'Wrong email or password. Please try again',
                        [],
                        Front::MESSAGE_DOMAIN
                    );
                } catch (CustomerNotConfirmedException $e) {
                    if ($e->getUser() !== null) {
                        // Send the confirmation email again
                        $eventDispatcher->dispatch(
                            new CustomerEvent($e->getUser()),
                            TheliaEvents::SEND_ACCOUNT_CONFIRMATION_EMAIL
                        );
                    }
                    $message = $this->getTranslator()->trans(
                        'Your account is not yet confirmed. A confirmation email has been sent to your email address, please check your mailbox',
                        [],
                        Front::MESSAGE_DOMAIN
                    );
                } catch (AuthenticationException $e) {
                    $message = $this->getTranslator()->trans(
                        'Wrong email or password. Please try again',
                        [],
                        Front::MESSAGE_DOMAIN
                    );
                }
            } catch (FormValidationException $e) {
                $message = $this->getTranslator()->trans(
                    'Please check your input: %s',
                    ['%s' => $e->getMessage()],
                    Front::MESSAGE_DOMAIN
                );
            } catch (\Exception $e) {
                $message = $this->getTranslator()->trans(
                    'Sorry, an error occured: %s',
                    ['%s' => $e->getMessage()],
                    Front::MESSAGE_DOMAIN
                );
            }

            Tlog::getInstance()->error(
                sprintf(
                    'Error during customer login process : %s. Exception was %s',
                    $message,
                    $e->getMessage()
                )
            );

            $customerLoginForm->setErrorMessage($message);

            $this->getParserContext()->addForm($customerLoginForm);

            if ($customerLoginForm->hasErrorUrl()) {
                return $this->generateErrorRedirect($customerLoginForm);
            }
        }
    }

    /**
     * Perform customer logout.
     */
    public function logoutAction(EventDispatcherInterface $eventDispatcher)
    {
        if ($this->getSecurityContext()->hasCustomerUser()) {
            $eventDispatcher->dispatch(new DefaultActionEvent(), TheliaEvents::CUSTOMER_LOGOUT);
        }

        $this->clearRememberMeCookie($this->getRememberMeCookieName());

        // Redirect to home page
        return $this->generateRedirect(URL::getInstance()->getIndexPage());
    }

    /**
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function confirmCustomerAction($token)
    {
        /** @var Customer $customer */
        if (null === $customer = CustomerQuery::create()->findOneByConfirmationToken($token)) {
            throw new NotFoundHttpException();
        }

        $customer
            ->setEnable(true)
            ->save()
        ;

        // Clear form error context

        return $this->generateRedirectFromRoute('customer.login.view', ['validation_done' => 1]);
    }

    /**
     * Dispatch event for customer login action.
     */
    protected function processLogin(EventDispatcherInterface $eventDispatcher, Customer $customer): void
    {
        $eventDispatcher->dispatch(new CustomerLoginEvent($customer), TheliaEvents::CUSTOMER_LOGIN);
    }

    /**
     * @return \Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent
     */
    private function createEventInstance($data)
    {
        $customerCreateEvent = new CustomerCreateOrUpdateEvent(
            $data['title'] ?? null,
            $data['firstname'] ?? null,
            $data['lastname'] ?? null,
            $data['address1'] ?? null,
            $data['address2'] ?? null,
            $data['address3'] ?? null,
            $data['phone'] ?? null,
            $data['cellphone'] ?? null,
            $data['zipcode'] ?? null,
            $data['city'] ?? null,
            $data['country'] ?? null,
            $data['email'] ?? null,
            $data['password'] ?? null,
            $data['lang_id'] ?? $this->getSession()->getLang()->getId(),
            $data['reseller'] ?? null,
            $data['sponsor'] ?? null,
            $data['discount'] ?? null,
            $data['company'] ?? null,
            null,
            $data['state'] ?? null
        );

        return $customerCreateEvent;
    }

    protected function getRememberMeCookieName()
    {
        return ConfigQuery::read('customer_remember_me_cookie_name', 'crmcn');
    }

    protected function getRememberMeCookieExpiration()
    {
        return ConfigQuery::read('customer_remember_me_cookie_expiration', 2592000 /* 1 month */);
    }
}
