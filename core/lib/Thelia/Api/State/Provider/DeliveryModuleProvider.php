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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Addressing\Service\AddressService;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Address;
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
        $deliveryAddress = $request instanceof Request
            ? $this->addressService->getDeliveryAddress($request, $this->securityContext)
            : null;
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
}
