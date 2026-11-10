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

namespace Thelia\Domain\Subscription;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\RecurringPaymentModuleInterface;

/**
 * The payment module that charges the installments of a subscription, when it can.
 *
 * Resolved the way CustomerPaymentMethodService resolves a module with stored methods:
 * the instance the container built for an active module, or one built from its namespace
 * and given the container. A module that is gone, deactivated, not recurring or that says
 * it cannot charge right now resolves to none, and the installment is then declined.
 */
final readonly class RecurringPaymentModuleResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function chargingModule(int $paymentModuleId): ?RecurringPaymentModuleInterface
    {
        $module = ModuleQuery::create()->findPk($paymentModuleId);

        if (!$module instanceof Module || BaseModule::IS_ACTIVATED !== (int) $module->getActivate()) {
            return null;
        }

        $instance = $this->instanceOf($module);

        return $instance instanceof RecurringPaymentModuleInterface && $instance->supportsRecurringCharges() ? $instance : null;
    }

    private function instanceOf(Module $module): ?object
    {
        if ($this->container->has('module.'.$module->getCode())) {
            return $module->getModuleInstance($this->container);
        }

        $moduleClass = $module->getFullNamespace();

        if (null === $moduleClass || !class_exists($moduleClass)) {
            return null;
        }

        $instance = $module->createInstance();
        $instance->setContainer($this->container);

        return $instance;
    }
}
