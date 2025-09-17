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

namespace Thelia\Domain\Customer;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\Authentication\CustomerUsernamePasswordFormAuthenticator;
use Thelia\Core\Security\Exception\CustomerNotConfirmedException;
use Thelia\Core\Security\Exception\WrongPasswordException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Exception\CustomerNotEnabledException;
use Thelia\Domain\Customer\Service\CustomerAuthenticator;
use Thelia\Domain\Customer\Service\CustomerCodeManager;
use Thelia\Domain\Customer\Service\CustomerRegistrationService;
use Thelia\Domain\Customer\Service\CustomerRememberMeService;
use Thelia\Form\CustomerLogin;
use Thelia\Model\Customer;

readonly class CustomerFacade
{
    public function __construct(
        private CustomerAuthenticator $customerAuthenticator,
        private CustomerRememberMeService $customerRememberMeService,
        private RequestStack $requestStack,
        private CustomerRegistrationService $customerRegistrationService,
        private SecurityContext $securityContext,
        private CustomerCodeManager $customerCodeManager,
    ) {
    }

    /**
     * Authenticate a customer with credentials, handle session + remember-me cookie.
     *
     * @throws CustomerNotConfirmedException
     * @throws WrongPasswordException
     * @throws CustomerNotEnabledException
     */
    public function login(CustomerLogin $customerLoginForm): void
    {
        $request = $this->requestStack->getMainRequest();

        $authenticator = new CustomerUsernamePasswordFormAuthenticator($request, $customerLoginForm);

        /** @var Customer $customer */
        $customer = $authenticator->getAuthentifiedUser();

        $this->customerAuthenticator->processLogin($customer);

        if ($customerLoginForm->getForm()->get('remember_me')->getData()) {
            $this->customerRememberMeService->createRememberMeCookie(
                $customer
            );
        }
    }

    /**
     * Logout the current customer and clear remember-me cookie.
     */
    public function logout(): void
    {
        $this->customerAuthenticator->processLogout();
    }

    /**
     * Return the currently authenticated customer, or null if guest.
     */
    public function getCurrentCustomer(): ?Customer
    {
        return $this->securityContext->getCustomerUser();
    }

    /**
     * Convenience helper: is a customer currently authenticated?
     */
    public function isLoggedIn(): bool
    {
        return null !== $this->getCurrentCustomer();
    }

    /**
     * Register a new customer account (front registration flow).
     * Returns the created Customer (may be disabled/pending confirmation based on business rules).
     */
    public function register(CustomerRegisterDTO $customerRegisterDTO): Customer
    {
        return $this->customerRegistrationService->registerCustomer($customerRegisterDTO);
    }

    /**
     * Resend an account code email to the given address.
     */
    public function sendCode(Customer $customer): void
    {
        $this->customerCodeManager->createCodeAndSendIt($customer);
    }
}
