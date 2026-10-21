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

        // A request that names no other address asks for the carriers of the cart: its own copy
        // of the delivery address decides, as CartGuard judges it when the order is placed, and
        // not the customer address behind it, which may have been edited since (or may be none,
        // for a guest whose address was typed in the API and never saved).
        $copy = $cart->getCartAddressRelatedByAddressDeliveryId();
        $namedAddress = $this->anotherAddressNamedBy($request, $copy?->getAddressId());

        if (null !== $copy && null === $namedAddress) {
            return $this->shippingFacade->listValidMethodsAsResourceApi($cart);
        }

        $deliveryAddress = $namedAddress ?? ($request instanceof Request
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
     * The address the request names when it is another one than the one the cart's copy was
     * taken from: an id AddressService reads as one (a strictly positive integer) that
     * resolves to an address. The source of the copy is the copy's to judge.
     */
    private function anotherAddressNamedBy(?HttpRequest $request, ?int $copySourceId): ?Address
    {
        $addressId = $request instanceof HttpRequest ? $this->addressService->requestedAddressId($request) : null;

        if (null === $addressId || $addressId === $copySourceId) {
            return null;
        }

        return AddressQuery::create()->findPk($addressId);
    }
}
