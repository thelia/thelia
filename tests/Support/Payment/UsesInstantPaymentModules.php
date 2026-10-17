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
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Installs the payment modules of the split payment tests the way an activated module is
 * installed: a `module` row, written in the test transaction, and the `module.<code>`
 * service the core resolves the instance through. The kernel is booted again by every
 * test, so the services go with the container; they are taken out all the same.
 *
 * The rows are written one after the other, so they come in that order at the bottom of
 * the positions: the order the core settles the instant parts in.
 */
trait UsesInstantPaymentModules
{
    /**
     * @var list<class-string<BaseModule>>
     */
    private array $installedPaymentModuleClasses = [];

    /**
     * @param class-string<BaseModule> $moduleClass
     */
    private function installPaymentModule(string $moduleClass, ContainerInterface $container, ConnectionInterface $connection): Module
    {
        $module = ModuleQuery::create()->findOneByCode($moduleClass::getModuleCode(), $connection);

        if (!$module instanceof Module) {
            $module = (new Module())
                ->setCode($moduleClass::getModuleCode())
                ->setFullNamespace($moduleClass)
                ->setVersion('1.0.0')
                ->setType(BaseModule::PAYMENT_MODULE_TYPE)
                ->setCategory('payment')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $module->save($connection);
        }

        $instance = new $moduleClass();
        $instance->setContainer($container);
        $container->set('module.'.$moduleClass::getModuleCode(), $instance);
        $this->installedPaymentModuleClasses[] = $moduleClass;

        return $module;
    }

    private function uninstallThePaymentModules(ContainerInterface $container): void
    {
        foreach ($this->installedPaymentModuleClasses as $moduleClass) {
            $container->set('module.'.$moduleClass::getModuleCode(), null);
        }

        $this->installedPaymentModuleClasses = [];
        InstantTestPaymentModule::reset();
        SecondInstantTestPaymentModule::reset();
        BalanceChargingTestPaymentModule::reset();
    }
}
