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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModule;
use Thelia\Api\Resource\ModuleI18n;
use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Api\State\Collection\DeliveryModuleCollection;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\State;
use Thelia\Module\BaseModule;
use Thelia\Module\Exception\DeliveryException;

readonly class DeliveryService
{
    private const SESSION_PREFIX = 'thelia.delivery.';

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ContainerInterface $container,
        private DataAccessService $dataAccessService,
        private Session $session,
        private CartService $cartService,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function getDeliveryModuleResource(
        Module $theliaDeliveryModule,
        Cart $cart,
        ?Address $address,
        Country $country,
        ?State $state,
        bool $onlyValid = false,
    ): ?DeliveryModule {
        $areaDeliveryModule = AreaDeliveryModuleQuery::create()
            ->findByCountryAndModule($country, $theliaDeliveryModule, $state);
        $isCartVirtual = $cart->isVirtual();

        $isValid = true;
        if (false === $isCartVirtual && null === $areaDeliveryModule) {
            $isValid = false;
        }

        $moduleInstance = $theliaDeliveryModule->getDeliveryModuleInstance($this->container);

        if (true === $isCartVirtual && false === $moduleInstance->handleVirtualProductDelivery()) {
            $isValid = false;
        }

        $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, $address, $country, $state);
        try {
            $this->dispatcher->dispatch(
                $deliveryPostageEvent,
                TheliaEvents::MODULE_DELIVERY_GET_POSTAGE
            );
        } catch (DeliveryException) {
            $isValid = false;
        }

        if (!$deliveryPostageEvent->isValidModule()) {
            $isValid = false;
        }

        if (!$isValid && $onlyValid) {
            return null;
        }

        $deliveryModuleOptionEvent = new DeliveryModuleOptionEvent($theliaDeliveryModule, $address, $cart, $country, $state);

        $this->dispatcher->dispatch(
            $deliveryModuleOptionEvent,
            TheliaEvents::MODULE_DELIVERY_GET_OPTIONS
        );

        $deliveryModuleApi = (new DeliveryModule())
            ->setId($theliaDeliveryModule->getId())
            ->setCode($theliaDeliveryModule->getCode())
            ->setValid($isValid)
            ->setDeliveryMode($deliveryPostageEvent->getDeliveryMode()?->value)
            ->setPosition($theliaDeliveryModule->getPosition())
            ->setOptions($deliveryModuleOptionEvent->getDeliveryModuleOptions());

        foreach ($theliaDeliveryModule->getModuleI18ns() as $i18n) {
            $deliveryModuleApi->addI18n(new ModuleI18n($i18n->toArray()), $i18n->getLocale());
        }

        return $deliveryModuleApi;
    }

    public function findDeliveryModeByModuleId(int $moduleId, DeliveryModuleCollection $collection): ?string
    {
        $allModules = $collection->getAll();

        foreach ($allModules as $module) {
            if ($module['id'] === $moduleId) {
                return $module['deliveryMode'];
            }
        }

        return null;
    }

    public function setDeliveryData(string $key, mixed $value): self
    {
        $this->session->set(self::SESSION_PREFIX.$key, $value);

        return $this;
    }

    public function getDeliveryData(string $key, mixed $default = null): mixed
    {
        return $this->session->get(self::SESSION_PREFIX.$key, $default);
    }

    public function getAllDeliveryData(): array
    {
        $allKeys = array_keys($_SESSION ?? []);
        $deliveryData = [];

        foreach ($allKeys as $key) {
            if (str_starts_with($key, self::SESSION_PREFIX)) {
                $cleanKey = str_replace(self::SESSION_PREFIX, '', $key);
                $deliveryData[$cleanKey] = $this->session->get($key);
            }
        }

        return $deliveryData;
    }

    public function clearDeliveryData(): self
    {
        $allKeys = array_keys($_SESSION ?? []);
        foreach ($allKeys as $key) {
            if (str_starts_with($key, self::SESSION_PREFIX)) {
                $this->session->remove($key);
            }
        }

        return $this;
    }

    public function setCustomerDefaultDeliveryAddress(): void
    {
        $cart = $this->cartService->getCart();
        $customer = $cart->getCustomer();

        if (!$customer) {
            return;
        }

        $defaultAddress = $customer->getDefaultAddress();

        if (!$defaultAddress) {
            throw new \RuntimeException(Translator::getInstance()->trans('Customer default address is null'));
        }

        $this->cartService->setDeliveryAddress($defaultAddress->getId());
    }

    public function setupVirtualDelivery(): void
    {
        $virtualDeliveryModule = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->filterByCode('VirtualProductDelivery')
            ->findOne();

        if (!$virtualDeliveryModule) {
            throw new \RuntimeException(Translator::getInstance()->trans('Virtual delivery module not found'));
        }

        $cart = $this->cartService->getCart();
        $customer = $cart->getCustomer();

        if ($customer) {
            $defaultAddress = AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->filterByIsDefault(1)
                ->findOne();

            if (!$defaultAddress) {
                $defaultAddress = AddressQuery::create()
                    ->filterByCustomerId($customer->getId())
                    ->findOne();
            }

            if ($defaultAddress) {
                $this->cartService->setDeliveryAddress($defaultAddress->getId());
            }
        }

        $this->cartService->setDeliveryModule($virtualDeliveryModule->getId());
        $this->cartService->handlePostageOnCart();
    }

    public function getValidDeliveryModuleCollection(): DeliveryModuleCollection
    {
        return $this->getDeliveryModuleCollection();
    }

    public function getAllDeliveryModuleCollection(): DeliveryModuleCollection
    {
        return $this->getDeliveryModuleCollection(false);
    }

    protected function getDeliveryModuleCollection(bool $onlyValid = true): DeliveryModuleCollection
    {
        $modules = $this->dataAccessService->resources(
            '/api/front/delivery_modules',
            ['only_valid' => $onlyValid ? 1 : 0]
        ) ?? [];

        return new DeliveryModuleCollection($modules);
    }
}
