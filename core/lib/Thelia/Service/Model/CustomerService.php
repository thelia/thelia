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

namespace Thelia\Service\Model;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateMinimalEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Authentication\CustomerUsernamePasswordFormAuthenticator;
use Thelia\Core\Security\Exception\CustomerNotConfirmedException;
use Thelia\Core\Security\Exception\UsernameNotFoundException;
use Thelia\Core\Security\Exception\WrongPasswordException;
use Thelia\Core\Security\Token\CookieTokenProvider;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\CustomerLogin;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleQuery;

class CustomerService
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function createCustomerMinimal(FormInterface $form): Customer
    {
        $customerCreateEvent = new CustomerCreateOrUpdateMinimalEvent();
        $customerCreateEvent->bindForm($form);
        $customerCreateEvent->setTitle($this->getDefaultCustomerTitle()->getId());

        if (!ConfigQuery::isCustomerEmailConfirmationEnable()) {
            $customerCreateEvent->setEnabled(true);
        }

        $this->dispatcher->dispatch($customerCreateEvent, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        return $customerCreateEvent->getCustomer();
    }

    public function updateCustomerMinimal(FormInterface $form, ?int $customerId = null): void
    {
        if (!$customer = CustomerQuery::create()->findPk($customerId)) {
            if (!$customer = $this->getCustomerInSession()) {
                throw new \Exception('Customer not found');
            }
        }

        $customerChangeEvent = new CustomerCreateOrUpdateMinimalEvent();
        $customerChangeEvent->bindForm($form);

        if (!ConfigQuery::isCustomerEmailConfirmationEnable()) {
            $customerChangeEvent->setEnabled(true);
        }

        $customerChangeEvent->setCustomer($customer);

        $this->dispatcher->dispatch($customerChangeEvent, TheliaEvents::CUSTOMER_UPDATEPROFILE);
    }

    /**
     * @throws CustomerNotConfirmedException
     * @throws UsernameNotFoundException
     * @throws WrongPasswordException
     */
    public function login(CustomerLogin $customerLoginForm): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $authenticator = new CustomerUsernamePasswordFormAuthenticator($request, $customerLoginForm);

        /** @var Customer $customer */
        $customer = $authenticator->getAuthentifiedUser();

        $this->processLogin($customer);

        if ($customerLoginForm->getForm()->get('remember_me')->getData()) {
            $this->createRememberMeCookie(
                $customer,
                $this->getRememberMeCookieName(),
                $this->getRememberMeCookieExpiration()
            );
        }
    }

    public function logout(): void
    {
        $this->dispatcher->dispatch(new DefaultActionEvent(), TheliaEvents::CUSTOMER_LOGOUT);
        $this->clearRememberMeCookie($this->getRememberMeCookieName());
    }

    public function processLogin(Customer $customer): void
    {
        if (!$customer->getEnable()) {
            throw new \Exception(Translator::getInstance()->trans('Customer account is disabled'));
        }

        $this->dispatcher->dispatch(new CustomerLoginEvent($customer), TheliaEvents::CUSTOMER_LOGIN);
    }

    public function getDefaultCustomerTitle(): ?CustomerTitle
    {
        return CustomerTitleQuery::create()
            ->filterByByDefault(1)
            ->findOne();
    }

    public function getCustomerInSession(): ?Customer
    {
        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession();

        /** @var Customer $customer */
        $customer = $session->getCustomerUser();

        return $customer;
    }

    /**
     * @throws \Exception
     */
    public function customerActivationByCode(string $email, int $code): void
    {
        $customer = CustomerQuery::create()->findOneByEmail($email);
        if (!$customer) {
            throw new \Exception('Customer not found');
        }

        $customer->verifyActivationCode((string) $code);

        $customer->setEnable(1)->save();
    }

    protected function getRememberMeCookieName()
    {
        return ConfigQuery::read('customer_remember_me_cookie_name', 'crmcn');
    }

    protected function getRememberMeCookieExpiration()
    {
        return ConfigQuery::read('customer_remember_me_cookie_expiration', 2592000 /* 1 month */);
    }

    protected function createRememberMeCookie(UserInterface $user, $cookieName, $cookieExpiration): void
    {
        $ctp = new CookieTokenProvider();

        $ctp->createCookie(
            $user,
            $cookieName,
            $cookieExpiration
        );
    }

    protected function clearRememberMeCookie($cookieName): void
    {
        $ctp = new CookieTokenProvider();

        $ctp->clearCookie($cookieName);
    }
}
