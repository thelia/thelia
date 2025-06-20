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

use Thelia\Model\Address;
use RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Service\Resource\DeliveryModuleApiService;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\Module;
use Thelia\Model\StateQuery;
use Thelia\Service\Model\AddressService;
use Thelia\Service\Model\DeliveryModuleService;

class DeliveryModuleProvider implements ProviderInterface
{
    public function __construct(
        private readonly Request $request,
        private readonly Session $session,
        private readonly SecurityContext $securityContext,
        private readonly AddressService $addressService,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly DeliveryModuleService $deliveryModuleService,
        private readonly DeliveryModuleApiService $deliveryModuleApiService
    ) {
    }

    /**
     * @throws PropelException
     * @throws RuntimeException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $cart = $this->session->getSessionCart($this->dispatcher);
        if (null === $cart) {
            return null;
        }

        $deliveryAddress = $this->addressService->getDeliveryAddress($this->request, $this->securityContext);
        $country = $deliveryAddress instanceof Address
            ? $deliveryAddress->getCountry()
            : CountryQuery::create()->filterByByDefault(1)->findOne();
        if (null === $country) {
            throw new RuntimeException(Translator::getInstance()->trans('You must either pass an address id or have a customer connected'));
        }

        $state = $deliveryAddress instanceof Address
            ? $deliveryAddress->getState()
            : StateQuery::create()->filterByCountryId($country->getId())->findOne();

        $modules = $this->deliveryModuleService->getDeliveryModules();
        $deliveryModules = [];
        /** @var Module $module */
        foreach ($modules as $module) {
            $deliveryModules[] = $this->deliveryModuleApiService->getDeliveryModuleResource(
                $module,
                $cart,
                $deliveryAddress,
                $country,
                $state
            );
        }

        if ($context['filters']['by_code'] ?? null === '1') {
            $deliveryModules = array_reduce($deliveryModules, function (array $carry, $item) {
                $carry[$item->getDeliveryMode()][] = $item;

                return $carry;
            }, []);
        }

        return $deliveryModules;
    }
}
