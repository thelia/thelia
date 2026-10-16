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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\TestToken;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

/**
 * A stored payment method names its module under a foreign key that forbids the
 * deletion: removing the module asks it to forget the methods, then drops them.
 *
 * The module row names a class that lives in the test suite and no directory under the
 * module folders, so the deletion has no file to remove.
 */
final class ModuleDeletionForgetsStoredPaymentMethodsTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        StoredMethodsPaymentModule::reset();
    }

    protected function tearDown(): void
    {
        StoredMethodsPaymentModule::reset();

        parent::tearDown();
    }

    public function testDeletingAModuleForgetsAndDropsTheMethodsStoredThroughIt(): void
    {
        [$module, $methodIds] = $this->moduleWithTwoStoredMethods();

        $this->dispatch(new ModuleDeleteEvent((int) $module->getId()), TheliaEvents::MODULE_DELETE);

        self::assertNull(ModuleQuery::create()->findPk($module->getId()), 'The module is gone.');
        self::assertSame(0, CustomerPaymentMethodQuery::create()->filterById($methodIds)->count(), 'Its stored methods with it.');

        $told = StoredMethodsPaymentModule::$forgottenMethodIds;
        sort($told);
        self::assertSame($methodIds, $told, 'The provider was asked to forget each of them.');
        self::assertSame(['forget', 'forget', 'destroy'], StoredMethodsPaymentModule::$calls, 'Before the module is destroyed, while its classes are still on disk.');
    }

    public function testAProviderThatRefusesDoesNotHoldTheDeletionBack(): void
    {
        [$module, $methodIds] = $this->moduleWithTwoStoredMethods();
        StoredMethodsPaymentModule::$providerRefusesToForget = true;

        $this->dispatch(new ModuleDeleteEvent((int) $module->getId()), TheliaEvents::MODULE_DELETE);

        self::assertNull(ModuleQuery::create()->findPk($module->getId()));
        self::assertSame(0, CustomerPaymentMethodQuery::create()->filterById($methodIds)->count());
    }

    /**
     * A module whose class is gone cannot be asked anything: its methods are dropped all
     * the same, or the foreign key would keep the row forever.
     */
    public function testDeletingAModuleWhoseClassIsGoneStillDropsItsMethods(): void
    {
        [$module, $methodIds] = $this->moduleWithTwoStoredMethods();
        $module->setFullNamespace('StoredMethodsModuleRemovedFromDisk\\StoredMethodsModuleRemovedFromDisk')->save($this->getPropelConnection());

        $this->dispatch(new ModuleDeleteEvent((int) $module->getId()), TheliaEvents::MODULE_DELETE);

        self::assertNull(ModuleQuery::create()->findPk($module->getId()));
        self::assertSame(0, CustomerPaymentMethodQuery::create()->filterById($methodIds)->count());
        self::assertSame([], StoredMethodsPaymentModule::$forgottenMethodIds, 'Nobody was left to tell.');
    }

    /**
     * An active module whose directory lacks its descriptor fails the dependency check
     * before anything else: its methods have to go all the same.
     */
    public function testDeletingAModuleMissingItsDescriptorStillDropsItsMethods(): void
    {
        [$module, $methodIds] = $this->moduleWithTwoStoredMethods();
        $module->setActivate(BaseModule::IS_ACTIVATED)->save($this->getPropelConnection());
        $moduleDirectory = THELIA_LOCAL_MODULE_DIR.$module->getBaseDir();
        $filesystem = new Filesystem();
        self::assertDirectoryDoesNotExist($moduleDirectory, 'The test needs a directory of its own.');
        $filesystem->mkdir($moduleDirectory);

        try {
            $this->dispatch(new ModuleDeleteEvent((int) $module->getId()), TheliaEvents::MODULE_DELETE);
        } finally {
            $filesystem->remove($moduleDirectory);
        }

        self::assertNull(ModuleQuery::create()->findPk($module->getId()));
        self::assertSame(0, CustomerPaymentMethodQuery::create()->filterById($methodIds)->count());
    }

    /**
     * A customer whose default method goes with the module, and who keeps another one
     * through a second module, is not left without a default.
     */
    public function testACustomerLosingTheirDefaultWithTheModuleGetsTheirRemainingMethodAsDefault(): void
    {
        $service = $this->getService(CustomerPaymentMethodService::class);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $removed = $this->moduleRow(self::moduleCode());
        $kept = $this->moduleRow(self::moduleCode().'Kept');

        $service->register($customer, $removed, 'pm_removed', 'Visa ending 1111', null);
        $remaining = $service->register($customer, $kept, 'pm_kept', 'Visa ending 2222', null);
        self::assertFalse($remaining->getIsDefault());

        $this->dispatch(new ModuleDeleteEvent((int) $removed->getId()), TheliaEvents::MODULE_DELETE);

        self::assertTrue(CustomerPaymentMethodQuery::create()->findPk($remaining->getId())->getIsDefault());
    }

    /**
     * @return array{Module, list<int>}
     */
    private function moduleWithTwoStoredMethods(): array
    {
        $module = $this->moduleRow(self::moduleCode());
        $service = $this->getService(CustomerPaymentMethodService::class);
        $customer = $this->factory->customer($this->factory->customerTitle());

        $methodIds = [
            (int) $service->register($customer, $module, 'pm_1', 'Visa ending 1111', null)->getId(),
            (int) $service->register($customer, $module, 'pm_2', 'Visa ending 2222', null)->getId(),
        ];
        sort($methodIds);

        return [$module, $methodIds];
    }

    /**
     * The deletion removes the directory named after the code, and local/modules is shared
     * by every test process: one running with a TEST_TOKEN puts its number in the code
     * (StoredMethodsPaymentModule2), so that it never creates, nor deletes, the directory
     * of a module another process is testing.
     */
    private static function moduleCode(): string
    {
        return StoredMethodsPaymentModule::getModuleCode().(TestToken::current() ?? '');
    }

    /**
     * The code names the row, the class is the same: nothing in the core ties one to the
     * other when the module is not in the container.
     */
    private function moduleRow(string $code): Module
    {
        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace(StoredMethodsPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }
}
