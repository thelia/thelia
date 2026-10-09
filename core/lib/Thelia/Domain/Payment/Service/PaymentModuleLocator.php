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
use Thelia\Model\Order;
use Thelia\Module\BaseModule;
use Thelia\Module\PaymentModuleInterface;

/**
 * The payment module of an order, ready to be called: the instance the container built
 * when the module is active, a fresh one given the container otherwise.
 * Order::getPaymentModuleInstance() alone hands out an instance without a container,
 * on which a module reaching its services fails.
 */
final readonly class PaymentModuleLocator
{
    public function __construct(
        #[Autowire(service: 'service_container')]
        private ContainerInterface $container,
    ) {
    }

    /**
     * @throws TheliaProcessException when the module of the order is not installed anymore
     */
    public function paymentModuleOf(Order $order): PaymentModuleInterface
    {
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
}
