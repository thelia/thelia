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

namespace Thelia\Domain\Payment\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;

/**
 * The payment module of an order, ready to be called: the instance the container built,
 * or a fresh one given the container. Order::getPaymentModuleInstance() alone hands out an
 * instance without a container, on which a module reaching its services fails.
 * A deactivated module is not called: its services are no longer compiled.
 */
final readonly class PaymentModuleLocator
{
    public function __construct(
        #[Autowire(service: 'service_container')]
        private ContainerInterface $container,
    ) {
    }

    /**
     * @throws TheliaProcessException when the module of the order is not installed or not active anymore
     */
    public function paymentModuleOf(Order $order): PaymentModuleInterface
    {
        $moduleRow = ModuleQuery::create()->findPk($order->getPaymentModuleId());

        if (null !== $moduleRow && BaseModule::IS_ACTIVATED !== (int) $moduleRow->getActivate()) {
            throw new TheliaProcessException(\sprintf('The payment module of order "%s" is not active anymore (%s).', (string) $order->getRef(), (string) $moduleRow->getCode()));
        }

        $module = $order->getPaymentModuleInstance();
        $serviceId = 'module.'.$module->getCode();

        if ($this->container->has($serviceId)) {
            $service = $this->container->get($serviceId);

            if ($service instanceof PaymentModuleInterface) {
                return $service;
            }
        }

        if ($module instanceof BaseModule) {
            $module->setContainer($this->container);
        }

        return $module;
    }

    /**
     * How a refusal names the module: its code while it is installed, the title written on
     * the order otherwise.
     */
    public function nameOf(Order $order): ?string
    {
        return ModuleQuery::create()->findPk($order->getPaymentModuleId())?->getCode() ?? $order->getPaymentModuleTitle();
    }
}
