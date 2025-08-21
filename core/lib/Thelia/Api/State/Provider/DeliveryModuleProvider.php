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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\CountryQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\StateQuery;
use Thelia\Module\BaseModule;
use Thelia\Service\Model\AddressService;
use Thelia\Service\Model\DeliveryService;

class DeliveryModuleProvider implements ProviderInterface
{
    public function __construct(
        private readonly Request $request,
        private readonly Session $session,
        private readonly SecurityContext $securityContext,
        private readonly AddressService $addressService,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly DeliveryService $deliveryModuleService,
    ) {
    }

    /**
     * @throws PropelException
     * @throws \RuntimeException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $cart = $this->session->getSessionCart($this->dispatcher);

        if (!$cart instanceof Cart) {
            return null;
        }

        $deliveryAddress = $this->addressService->getDeliveryAddress($this->request, $this->securityContext);
        $country = $deliveryAddress instanceof Address
            ? $deliveryAddress->getCountry()
            : CountryQuery::create()->filterByByDefault(1)->findOne();

        if (null === $country) {
            throw new \RuntimeException(Translator::getInstance()->trans('You must either pass an address id or have a customer connected'));
        }

        $state = $deliveryAddress instanceof Address
            ? $deliveryAddress->getState()
            : StateQuery::create()->filterByCountryId($country->getId())->findOne();

        $modules = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->find();

        $deliveryModules = [];

        /** @var Module $module */
        foreach ($modules as $module) {
            /** @var ?int $filterOnlyValid */
            $filterOnlyValid = $context['filters']['only_valid'] ?? false;
            $deliveryModuleResource = $this->deliveryModuleService->getDeliveryModuleResource(
                $module,
                $cart,
                $deliveryAddress,
                $country,
                $state,
                $filterOnlyValid !== null && (bool) $filterOnlyValid
            );

            if ($deliveryModuleResource) {
                $deliveryModules[] = $deliveryModuleResource;
            }
        }

        return $deliveryModules;
    }
}
