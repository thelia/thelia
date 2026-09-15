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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Resource\GuestCustomer;
use Thelia\Api\Security\GuestTokenClaims;
use Thelia\Domain\Customer\CustomerFacade;
use Thelia\Domain\Customer\Exception\GuestCheckoutEmailAlreadyRegisteredException;
use Thelia\Domain\Customer\Exception\GuestConversionPendingException;
use Thelia\Domain\Customer\Exception\NotAGuestCustomerException;
use Thelia\Domain\Order\Service\GuestOrderAccessLimiter;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Turns the guest account into a real one, for whoever can prove they are behind it.
 *
 * There are exactly two proofs, and neither of them is the address: knowing an address
 * must never be enough to set a password on the account that carries its orders. Either
 * the caller holds the guest token of the registration that opened the row — not one
 * handed out later to whoever typed the same address, since a guest row is reused across
 * visits — or it holds a tracking token for one of the orders on that account, which is
 * signed, expires, and is rate limited before it is even checked.
 *
 * The two proofs are not worth the same. A tracking token was mailed to the address, so
 * holding it means having read the mailbox, and that is enough to replace a password
 * somebody else has chosen and not yet confirmed. A guest token was handed out over the
 * counter; it may set a password on a row that has none, and no more.
 */
final readonly class GuestCustomerConversionProcessor implements ProcessorInterface
{
    public function __construct(
        private GuestTokenClaims $guestTokenClaims,
        private GuestOrderAccessService $guestOrderAccessService,
        private GuestOrderAccessLimiter $guestOrderAccessLimiter,
        private CustomerFacade $customerFacade,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GuestCustomer
    {
        if (!$data instanceof GuestCustomer) {
            throw new \LogicException(\sprintf('Expected a %s, got %s.', GuestCustomer::class, get_debug_type($data)));
        }

        $customerId = $this->requestedCustomerId($uriVariables);
        $customer = null === $customerId ? null : CustomerQuery::create()->findPk($customerId);

        $readTheMailbox = $customer instanceof Customer && $this->holdsATrackingTokenFor($customer, $data->orderToken);

        // One answer for "no such account" and for "not yours": telling them apart would
        // turn the endpoint into a way to find out which customer ids are guests.
        if (!$customer instanceof Customer || (!$readTheMailbox && !$this->openedTheRow($customer))) {
            throw new AccessDeniedHttpException('This guest account cannot be completed with the credentials given.');
        }

        try {
            $converted = $this->customerFacade->convertGuestToCustomer(
                $customer,
                (string) $data->password,
                replacesPendingPassword: $readTheMailbox,
            );
        } catch (NotAGuestCustomerException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (GuestCheckoutEmailAlreadyRegisteredException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (GuestConversionPendingException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        // No token comes back on purpose: the account now has a password, and the way
        // in from here on is signing in with it. The guest token the caller may still
        // hold stays a guest token, and stops matching the tracking links it issued.
        $response = new GuestCustomer();
        $response->id = $converted->getId();
        $response->email = $converted->getEmail();
        $response->firstname = $converted->getFirstname();
        $response->lastname = $converted->getLastname();
        // The record is still a guest: the password is written, the account is not open,
        // and the code mailed to the address is what opens it.
        $response->activationCodeRequired = $converted->isGuest();

        return $response;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function requestedCustomerId(array $uriVariables): ?int
    {
        $id = $uriVariables['id']
            ?? $this->requestStack->getCurrentRequest()?->attributes->get('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The caller holds the guest token of the registration that opened this very row.
     *
     * A token bound to the row is not enough on its own: the row is reused when the same
     * address orders again, so a later registration on that address is handed a token
     * for it too, and all it took was knowing the address.
     */
    private function openedTheRow(Customer $customer): bool
    {
        return $this->guestTokenClaims->createdTheCustomer()
            && $this->guestTokenClaims->customer()?->getId() === $customer->getId();
    }

    private function holdsATrackingTokenFor(Customer $customer, ?string $orderToken): bool
    {
        if (null === $orderToken || '' === $orderToken) {
            return false;
        }

        // Spent before the token is checked, so a caller pays the same whether or not
        // the token it sent turns out to be one this shop issued.
        if (!$this->guestOrderAccessLimiter->allows($orderToken)) {
            return false;
        }

        return $this->guestOrderAccessService->findOrderForToken($orderToken)?->getCustomerId() === $customer->getId();
    }
}
