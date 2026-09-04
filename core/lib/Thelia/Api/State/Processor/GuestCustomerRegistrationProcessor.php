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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Resource\GuestCustomer;
use Thelia\Api\Security\GuestRegistrationLimiter;
use Thelia\Api\Security\GuestTokenIssuer;
use Thelia\Api\Service\GuestCheckoutSession;
use Thelia\Domain\Checkout\Service\GuestCheckoutPolicy;
use Thelia\Domain\Customer\CustomerFacade;
use Thelia\Domain\Customer\DTO\CustomerGuestDTO;
use Thelia\Domain\Customer\Exception\GuestCheckoutEmailAlreadyRegisteredException;

/**
 * Opens the passwordless account a guest checkout hangs off, and hands back the token
 * that is the whole of its authentication.
 *
 * The order of the gates matters. The rate limit is spent first, before the address is
 * looked up, so a caller cannot use the endpoint to find out which addresses have an
 * account by watching what it costs. Then the shop's own answer to "may this be
 * ordered without an account", which depends on what is in the cart, so the cart is
 * resolved before the customer row is written and nothing is created for a checkout
 * that is going to be refused anyway.
 */
final readonly class GuestCustomerRegistrationProcessor implements ProcessorInterface
{
    public function __construct(
        private GuestRegistrationLimiter $registrationLimiter,
        private GuestCheckoutPolicy $guestCheckoutPolicy,
        private GuestCheckoutSession $guestCheckoutSession,
        private CustomerFacade $customerFacade,
        private GuestTokenIssuer $guestTokenIssuer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GuestCustomer
    {
        if (!$data instanceof GuestCustomer) {
            throw new \LogicException(\sprintf('Expected a %s, got %s.', GuestCustomer::class, get_debug_type($data)));
        }

        $email = (string) $data->email;

        if (!$this->registrationLimiter->allows($email)) {
            throw new TooManyRequestsHttpException(message: 'Too many guest checkout registrations from here. Try again later.');
        }

        if (!$this->guestCheckoutPolicy->isGuestCheckoutEnabled()) {
            throw new AccessDeniedHttpException('This shop requires an account to place an order.');
        }

        $cart = $this->guestCheckoutSession->currentCart();

        if (!$this->guestCheckoutPolicy->isGuestCheckoutAllowedForCart($cart)) {
            throw new UnprocessableEntityHttpException('This cart holds a product that requires an account.');
        }

        try {
            $guest = $this->customerFacade->registerGuest(new CustomerGuestDTO(
                email: $email,
                firstname: $data->firstname,
                lastname: $data->lastname,
                title: $data->customerTitleId,
                langId: $data->langId,
            ));
        } catch (GuestCheckoutEmailAlreadyRegisteredException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        $claimedCart = $this->guestCheckoutSession->attachToGuest($cart, $guest);

        $response = new GuestCustomer();
        $response->id = $guest->getId();
        $response->email = $guest->getEmail();
        $response->firstname = $guest->getFirstname();
        $response->lastname = $guest->getLastname();
        $response->token = $this->guestTokenIssuer->issueFor($guest, $claimedCart);
        $response->expiresIn = $this->guestTokenIssuer->lifetimeInSeconds();
        $response->cartId = $claimedCart?->getId();

        return $response;
    }
}
