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
 * Installs StoredMethodsPaymentModule the way an activated module is installed: a
 * `module` row, written in the test transaction, and the `module.<code>` service the
 * payment listing and Action\Module::pay() resolve it through. The kernel is booted
 * again by every test, so the service goes with the container; it is taken out all
 * the same.
 */
trait UsesStoredMethodsPaymentModule
{
    private function installTheStoredMethodsPaymentModule(ContainerInterface $container, ConnectionInterface $connection): Module
    {
        StoredMethodsPaymentModule::reset();

        $module = ModuleQuery::create()->findOneByCode(StoredMethodsPaymentModule::getModuleCode(), $connection);

        if (!$module instanceof Module) {
            $module = (new Module())
                ->setCode(StoredMethodsPaymentModule::getModuleCode())
                ->setFullNamespace(StoredMethodsPaymentModule::class)
                ->setVersion('1.0.0')
                ->setType(BaseModule::PAYMENT_MODULE_TYPE)
                ->setCategory('payment')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $module->save($connection);
        }

        $instance = new StoredMethodsPaymentModule();
        $instance->setContainer($container);
        $container->set($this->storedMethodsPaymentModuleServiceId(), $instance);

        return $module;
    }

    private function uninstallTheStoredMethodsPaymentModule(ContainerInterface $container): void
    {
        $container->set($this->storedMethodsPaymentModuleServiceId(), null);
        StoredMethodsPaymentModule::reset();
    }

    private function storedMethodsPaymentModuleServiceId(): string
    {
        return 'module.'.StoredMethodsPaymentModule::getModuleCode();
    }

    /**
     * Written as a row, the way the provider's answer ends up, so that a test can give a
     * method to a customer the service would refuse (a guest), or to another module.
     */
    private function storedMethodRow(
        Customer $customer,
        Module $module,
        string $label,
        ?string $expiresAt = null,
        bool $default = false,
        ?ConnectionInterface $connection = null,
    ): CustomerPaymentMethod {
        $method = (new CustomerPaymentMethod())
            ->setCustomerId($customer->getId())
            ->setPaymentModuleId($module->getId())
            ->setToken('pm_secret_'.bin2hex(random_bytes(6)))
            ->setLabel($label)
            ->setExpiresAt($expiresAt)
            ->setIsDefault($default);
        $method->save($connection);

        return $method;
    }
}
