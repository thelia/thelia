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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Addressing\Service\AddressService;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CountryQuery;
use Thelia\Model\StateQuery;

readonly class DeliveryModuleProvider implements ProviderInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private Session $session,
        private SecurityContext $securityContext,
        private AddressService $addressService,
        private EventDispatcherInterface $dispatcher,
        private ShippingFacade $shippingFacade,
    ) {
    }

    /**
     * @throws PropelException
     * @throws \RuntimeException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        try {
            $cart = $this->session->getSessionCart($this->dispatcher);
        } catch (\Throwable) {
            return [];
        }

        if (!$cart instanceof Cart) {
            return [];
        }

        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        // A request that names no other address of the cart's customer asks for the carriers of
        // the cart: its own copy of the delivery address decides, as CartGuard judges it when
        // the order is placed, and not the customer address behind it, which may have been
        // edited since. The country and the state of an address of someone else would otherwise
        // be read out through the carriers listed for it.
        $copy = $cart->getCartAddressRelatedByAddressDeliveryId();
        $requestedId = $request instanceof HttpRequest ? $this->addressService->requestedAddressId($request) : null;
        $namedAddress = $this->anotherAddressOfTheCustomer($cart, $requestedId, $copy?->getAddressId());

        if (null !== $copy && null === $namedAddress) {
            return $this->shippingFacade->listValidMethodsAsResourceApi($cart);
        }

        // Another address of the customer named: that address. Otherwise, without a copy: the
        // address of the session when the request names none (an id that does not read as
        // one names none), the default country of the shop when it names an address that is
        // not the customer's.
        $deliveryAddress = $namedAddress ?? (null === $requestedId && $request instanceof Request
            ? $this->addressService->getDeliveryAddress($request, $this->securityContext)
            : null);

        $country = $deliveryAddress instanceof Address
            ? $deliveryAddress->getCountry()
            : CountryQuery::create()->filterByByDefault(1)->findOne();

        if (null === $country) {
            return [];
        }

        $state = $deliveryAddress instanceof Address
            ? $deliveryAddress->getState()
            : StateQuery::create()->filterByCountryId($country->getId())->findOne();

        return $this->shippingFacade->listValidMethodsAsResourceApi($cart, $country, $state, $deliveryAddress?->getId());
    }

    /**
     * The address the request names when it is another address of the cart's customer than
     * the one the cart's copy was taken from. Never for a guest: a guest row is reused by
     * whoever types the same e-mail address, so its other addresses may be other buyers'.
     */
    private function anotherAddressOfTheCustomer(Cart $cart, ?int $addressId, ?int $copySourceId): ?Address
    {
        $customer = $cart->getCustomer();

        if (null === $addressId || $addressId === $copySourceId || null === $customer || $customer->isGuest()) {
            return null;
        }

        return AddressQuery::create()
            ->filterById($addressId)
            ->filterByCustomerId($customer->getId())
            ->findOne();
    }
}
