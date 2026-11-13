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

namespace Thelia\Tests\Support\Payment;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Installs RecurringTestPaymentModule the way an activated module is installed: a `module`
 * row in the test transaction and the `module.<code>` service the core resolves it through.
 */
trait UsesRecurringTestPaymentModule
{
    private function installTheRecurringTestPaymentModule(ContainerInterface $container, ConnectionInterface $connection): Module
    {
        RecurringTestPaymentModule::reset();

        $module = ModuleQuery::create()->findOneByCode(RecurringTestPaymentModule::getModuleCode(), $connection);

        if (!$module instanceof Module) {
            $module = (new Module())
                ->setCode(RecurringTestPaymentModule::getModuleCode())
                ->setFullNamespace(RecurringTestPaymentModule::class)
                ->setVersion('1.0.0')
                ->setType(BaseModule::PAYMENT_MODULE_TYPE)
                ->setCategory('payment')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $module->save($connection);
        }

        $instance = new RecurringTestPaymentModule();
        $instance->setContainer($container);
        $container->set('module.'.RecurringTestPaymentModule::getModuleCode(), $instance);

        return $module;
    }

    private function uninstallTheRecurringTestPaymentModule(ContainerInterface $container): void
    {
        $container->set('module.'.RecurringTestPaymentModule::getModuleCode(), null);
        RecurringTestPaymentModule::reset();
    }

    private function recurringMethodOf(Customer $customer, Module $module, ?ConnectionInterface $connection = null): CustomerPaymentMethod
    {
        $method = (new CustomerPaymentMethod())
            ->setCustomerId($customer->getId())
            ->setPaymentModuleId($module->getId())
            ->setToken('pm_recurring_'.bin2hex(random_bytes(6)))
            ->setLabel('Visa ending 4242')
            ->setIsDefault(true);
        $method->save($connection);

        return $method;
    }
}
