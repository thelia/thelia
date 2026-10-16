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

namespace Thelia\Action;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\File\Exception\FileNotFoundException;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Service\HandlerCleaner;
use Thelia\Domain\Module\Exception\ModuleException;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Domain\Payment\Exception\StoredMethodPaymentRateLimitedException;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;
use Thelia\Domain\Payment\StoredPaymentMethodPayment;
use Thelia\Log\Tlog;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order as OrderModel;
use Thelia\Model\OrderQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\InstantPaymentModuleInterface;
use Thelia\Module\ModuleManagement;
use Thelia\Module\ModuleTransactionGuard;
use Thelia\Module\PaymentModuleInterface;
use Thelia\Module\PaymentModuleWithStoredMethodsInterface;
use Thelia\Module\Validator\ModuleValidator;

/**
 * Class Module.
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class Module extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        protected ContainerInterface $container,
        protected HandlerCleaner $handlerCleaner,
        protected CustomerPaymentMethodService $customerPaymentMethods,
        protected StoredPaymentMethodPayment $storedPaymentMethodPayment,
        protected OrderPaymentPartSettlement $orderPaymentPartSettlement,
    ) {
    }

    public function toggleActivation(ModuleToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null === $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            return;
        }

        if (!class_exists($module->getFullNamespace())) {
            // The module code is missing from disk (e.g. removed from composer while
            // still registered in the database). Allow deactivation so the database
            // can be cleaned up; activation is impossible without the code.
            if (BaseModule::IS_ACTIVATED !== $module->getActivate()) {
                throw new ModuleException(\sprintf('Cannot activate module "%s": its class "%s" was not found on disk.', $module->getCode(), $module->getFullNamespace()));
            }

            $module->setActivate(BaseModule::IS_NOT_ACTIVATED);
            $module->save();

            Tlog::getInstance()->addWarning(\sprintf(
                'Module "%s" was deactivated without running its lifecycle hooks: its class "%s" was not found on disk.',
                $module->getCode(),
                $module->getFullNamespace(),
            ));

            $event->setModule($module);

            $this->cacheClear($dispatcher);

            return;
        }

        $moduleInstance = $module->createInstance();

        if (method_exists($moduleInstance, 'setContainer')) {
            $moduleInstance->setContainer($this->container);

            if (BaseModule::IS_ACTIVATED === $module->getActivate()) {
                $moduleInstance->deActivate($module);
            } else {
                $moduleInstance->activate($module);
            }
        }

        $event->setModule($module);

        $this->cacheClear($dispatcher);
    }

    public function checkToggleActivation(ModuleToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if ($event->isNoCheck()) {
            return;
        }

        if (null === $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            return;
        }
        try {
            if (BaseModule::IS_ACTIVATED === $module->getActivate()) {
                if (BaseModule::IS_MANDATORY === $module->getMandatory() && false === $event->getAssumeDeactivate()) {
                    throw new \Exception(Translator::getInstance()->trans("Can't deactivate a secure module"));
                }

                if ($event->isRecursive()) {
                    $this->recursiveDeactivation($event, $eventName, $dispatcher);
                }

                $this->checkDeactivation($module);

                return;
            }
            if ($event->isRecursive()) {
                $this->recursiveActivation($event, $eventName, $dispatcher);
            }

            $this->checkActivation($module);
        } catch (\Exception $ex) {
            $event->stopPropagation();

            throw $ex;
        }
    }

    /**
     * Check if module can be activated : supported version of Thelia, module dependencies.
     *
     * @return bool true if the module can be activated, otherwise false
     *
     * @throws \Exception if activation fails
     */
    private function checkActivation(\Thelia\Model\Module $module): bool
    {
        $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());
        $moduleValidator->validate(false);

        return true;
    }

    /**
     * Check if module can be deactivated safely because other modules
     * could have dependencies to this module.
     *
     * @return bool true if the module can be deactivated, otherwise false
     */
    private function checkDeactivation(\Thelia\Model\Module $module): bool
    {
        // A module missing from disk declares no dependencies to other modules;
        // it must remain deactivatable so the database can be cleaned up.
        if (!is_dir($module->getAbsoluteBaseDir())) {
            return true;
        }

        $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());

        $modules = $moduleValidator->getModulesDependOf();

        if ([] !== $modules) {
            $moduleList = implode(', ', array_column($modules, 'code'));

            $message = (1 === \count($modules))
                ? Translator::getInstance()->trans(
                    '%s has dependency to module %s. You have to deactivate this module before.',
                )
                : Translator::getInstance()->trans(
                    '%s have dependencies to module %s. You have to deactivate these modules before.',
                );

            throw new ModuleException(\sprintf($message, $moduleList, $moduleValidator->getModuleDefinition()->getCode()));
        }

        return true;
    }

    /**
     * Get dependencies of the current module and activate it if needed.
     *
     * @throws \Exception
     */
    public function recursiveActivation(ModuleToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null === $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            return;
        }
        $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());
        $dependencies = $moduleValidator->getCurrentModuleDependencies();
        foreach ($dependencies as $defMod) {
            $submodule = ModuleQuery::create()
                ->findOneByCode($defMod['code']);

            if (!$submodule) {
                $dependencyCode = $defMod['code'];
                $absolutePathToSubModule = is_dir(THELIA_MODULE_DIR.$dependencyCode)
                    ? THELIA_MODULE_DIR.$dependencyCode
                    : THELIA_LOCAL_MODULE_DIR.$dependencyCode;
                $moduleInstallEvent = (new ModuleInstallEvent())
                    ->setModulePath($absolutePathToSubModule)
                    ->setModuleDefinition((new ModuleValidator($absolutePathToSubModule))->getModuleDefinition());

                $this->install($moduleInstallEvent, $eventName, $dispatcher);
                $submodule = $moduleInstallEvent->getModule();
            }

            if ($submodule && BaseModule::IS_ACTIVATED !== $submodule->getActivate()) {
                $subevent = new ModuleToggleActivationEvent($submodule->getId());
                $subevent->setRecursive(true);
                $dispatcher->dispatch($subevent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
            }
        }
    }

    /**
     * Get modules having current module in dependence and deactivate it if needed.
     */
    public function recursiveDeactivation(ModuleToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null === $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            return;
        }
        $moduleValidator = new ModuleValidator($module->getAbsoluteBaseDir());
        $dependencies = $moduleValidator->getModulesDependOf(true);

        foreach ($dependencies as $defMod) {
            $submodule = ModuleQuery::create()
                ->findOneByCode($defMod['code']);

            if ($submodule && BaseModule::IS_ACTIVATED === $submodule->getActivate()) {
                $subevent = new ModuleToggleActivationEvent($submodule->getId());
                $subevent->setRecursive(true);
                $dispatcher->dispatch($subevent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
            }
        }
    }

    public function delete(ModuleDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);

        if (null === $module = ModuleQuery::create()->findPk($event->getModuleId(), $con)) {
            return;
        }

        $con->beginTransaction();

        try {
            if (null === $module->getFullNamespace()) {
                throw new \LogicException(Translator::getInstance()->trans('Cannot instantiate module "%name%": the namespace is null. Maybe the model is not loaded ?', ['%name%' => $module->getCode()]));
            }

            // Orders reference the module through a foreign key that forbids the
            // deletion, so the name they display has to be kept before the row goes.
            $this->freezeModuleOnOrders($module, $con);

            try {
                if (BaseModule::IS_MANDATORY === $module->getMandatory() && false === $event->getAssumeDelete()) {
                    throw new \Exception(Translator::getInstance()->trans("Can't remove a core module"));
                }

                // First, try to create an instance
                $instance = $module->createInstance();

                // Then, if module is activated, check if we can deactivate it
                if ($module->getActivate()) {
                    // check for modules that depend of this one
                    $this->checkDeactivation($module);
                }

                $instance->setContainer($this->container);

                // The stored payment methods name the module under a foreign key that forbids
                // the deletion. The provider is told to forget them first, while the module's
                // classes are still on disk.
                $this->customerPaymentMethods->forgetAllOfModule($module, $con);

                $path = $module->getAbsoluteBaseDir();

                ModuleTransactionGuard::run($con, (string) $module->getCode(), 'destroy', static function () use ($instance, $con, $event): void {
                    $instance->destroy($con, $event->getDeleteData());
                });

                $fs = new Filesystem();
                $fs->remove($path);
            } catch (\ReflectionException) {
                // Happens probably because the module directory has been deleted.
                // Log a warning, and delete the database entry.
                Tlog::getInstance()->addWarning(
                    Translator::getInstance()->trans(
                        'Failed to create instance of module "%name%" when trying to delete module. Module directory has probably been deleted',
                        ['%name%' => $module->getCode()],
                    ),
                );
            } catch (FileNotFoundException) {
                // The module directory has been deleted.
                // Log a warning, and delete the database entry.
                Tlog::getInstance()->addWarning(
                    Translator::getInstance()->trans(
                        'Module "%name%" directory was not found',
                        ['%name%' => $module->getCode()],
                    ),
                );
            }

            // Again, for the paths above that stopped before the first call: a class gone
            // from disk, a module missing its descriptor. The provider is told when the class
            // still loads, the methods are dropped either way; after the first call there is
            // nothing left to drop.
            $this->customerPaymentMethods->forgetAllOfModule($module, $con);

            // The export and import tables carry no module id, so the entries the module
            // declared are matched on the namespace of their handler class.
            $this->handlerCleaner->removeHandlersProvidedBy((string) $module->getFullNamespace(), $con);

            $module->delete($con);

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }

        // Outside the try: these two run once the deletion is committed, so a
        // failure there no longer reaches a rollback of a closed transaction.
        $event->setModule($module);
        $this->cacheClear($dispatcher);
    }

    /**
     * An order names its payment and delivery methods through module ids, so an
     * invoice line or an export column is resolved from the module row at render
     * time. Write the name the module carries on the orders that used it, in the
     * language of each order, then release the two references so the row can go.
     */
    private function freezeModuleOnOrders(\Thelia\Model\Module $module, ConnectionInterface $con): void
    {
        $moduleId = $module->getId();

        $this->freezeModuleOnPaymentParts($module, $con);

        foreach (LangQuery::create()->find($con) as $lang) {
            $title = $module->setLocale($lang->getLocale())->getTitle() ?: $module->getCode();

            OrderQuery::create()
                ->filterByLangId($lang->getId())
                ->filterByPaymentModuleId($moduleId)
                ->update(['PaymentModuleTitle' => $title, 'PaymentModuleId' => null], $con);

            OrderQuery::create()
                ->filterByLangId($lang->getId())
                ->filterByDeliveryModuleId($moduleId)
                ->update(['DeliveryModuleTitle' => $title, 'DeliveryModuleId' => null], $con);
        }
    }

    /**
     * The payment parts of an order name the module too, and the foreign key would
     * release them without a trace: write the title first, in the language of their order.
     */
    private function freezeModuleOnPaymentParts(\Thelia\Model\Module $module, ConnectionInterface $con): void
    {
        $moduleId = $module->getId();

        $this->lockTheOrdersOfThePartsOfModule($moduleId, $con);

        // The code first, for the parts whose order has no language the title could be
        // read in; then the title of each language, on the parts of the orders written in it.
        $statement = $con->prepare(
            'UPDATE `order_payment_part` SET `payment_module_title` = :title WHERE `payment_module_id` = :module',
        );
        $statement->execute(['title' => $module->getCode(), 'module' => $moduleId]);

        $statement = $con->prepare(
            'UPDATE `order_payment_part` AS `part` INNER JOIN `order` ON `order`.`id` = `part`.`order_id`
             SET `part`.`payment_module_title` = :title
             WHERE `part`.`payment_module_id` = :module AND `order`.`lang_id` = :lang',
        );

        foreach (LangQuery::create()->find($con) as $lang) {
            $title = $module->setLocale($lang->getLocale())->getTitle() ?: $module->getCode();

            $statement->execute(['title' => $title, 'module' => $moduleId, 'lang' => $lang->getId()]);
        }

        OrderPaymentPartTableMap::clearInstancePool();
    }

    /**
     * The settlement and the release of a part lock the order by its primary key, then its
     * parts: the same order here, orders first and by increasing id, or the two meet in a
     * deadlock. The orders are found by a read that locks nothing, because a locking read
     * joining the parts would lock them through the index on the module, before the orders.
     */
    private function lockTheOrdersOfThePartsOfModule(int $moduleId, ConnectionInterface $con): void
    {
        $statement = $con->prepare(
            'SELECT DISTINCT `order_id` FROM `order_payment_part` WHERE `payment_module_id` = :module ORDER BY `order_id`',
        );
        $statement->execute(['module' => $moduleId]);

        foreach (array_chunk(array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN)), 500) as $orderIds) {
            $con
                ->query('SELECT `id` FROM `order` WHERE `id` IN ('.implode(',', $orderIds).') ORDER BY `id` FOR UPDATE')
                ->close();
        }
    }

    public function update(ModuleEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null === $module = ModuleQuery::create()->findPk($event->getId())) {
            return;
        }
        $module

            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setChapo($event->getChapo())
            ->setDescription($event->getDescription())
            ->setPostscriptum($event->getPostscriptum());

        $module->save();

        $event->setModule($module);
    }

    /**
     * @throws \Exception
     * @throws IOException
     * @throws \Exception
     */
    public function install(ModuleInstallEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $moduleDefinition = $event->getModuleDefinition();

        $oldModule = ModuleQuery::create()->findOneByFullNamespace($moduleDefinition->getNamespace());

        $activated = false;

        // Where the new files go. A new module lands in THELIA_MODULE_DIR; an upgrade goes
        // back where the module already lives, which may be THELIA_LOCAL_MODULE_DIR. Writing
        // it to THELIA_MODULE_DIR instead would leave two copies of the module on disk, and
        // Module::getModuleDir() gives the local one priority: the old code would shadow the new.
        $modulePathNewModule = \sprintf('%s%s', THELIA_MODULE_DIR, $moduleDefinition->getCode());

        // check existing module
        if (null !== $oldModule) {
            // The namespace is already installed: this is an upgrade, and the row is kept.
            // Deleting it would cascade to the hooks and their positions, the configuration,
            // the hooks the administrator switched off, the module images, the access
            // profiles, the delivery areas and the coupon conditions carrying its id.
            if ($oldModule->getCode() !== $moduleDefinition->getCode()) {
                throw new ModuleException(Translator::getInstance()->trans('The module in the archive is named "%new%" but the namespace "%namespace%" is already installed as "%old%". Uninstall "%old%" before installing "%new%".', ['%new%' => $moduleDefinition->getCode(), '%old%' => $oldModule->getCode(), '%namespace%' => $moduleDefinition->getNamespace()]));
            }

            $activated = BaseModule::IS_ACTIVATED === $oldModule->getActivate();

            $modulePathNewModule = $oldModule->getAbsoluteBaseDir();

            if ($activated) {
                // deactivate
                $toggleEvent = new ModuleToggleActivationEvent($oldModule->getId());
                // disable the check of the module because it's already done
                $toggleEvent->setNoCheck(true);

                $dispatcher->dispatch($toggleEvent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
            }
        }

        try {
            $this->replaceModuleFiles($event->getModulePath(), $modulePathNewModule);

            // Update the module
            $moduleDescriptorFile = \sprintf('%s%s%s%s%s', $modulePathNewModule, DS, 'Config', DS, 'module.xml');
            $eventDispatcher = $this->container->get('event_dispatcher');
            $moduleManagement = new ModuleManagement($this->container, $eventDispatcher);
            $file = new \SplFileInfo($moduleDescriptorFile);
            $module = $moduleManagement->updateModule($file, $this->container, true);
        } catch (\Throwable $throwable) {
            // The module was deactivated a few lines above so it could be replaced, and the
            // replacement is not going to happen. Put the shop back where it was: a payment
            // or delivery module left deactivated by a failed upgrade takes the checkout
            // down, silently, while the administrator only reads that the install failed.
            if (null !== $oldModule) {
                $this->restoreActivation((int) $oldModule->getId(), $activated, $dispatcher);
            }

            throw $throwable;
        }

        // activate if old was activated
        if ($activated) {
            $toggleEvent = new ModuleToggleActivationEvent($module->getId());
            $toggleEvent->setNoCheck(true);

            $dispatcher->dispatch($toggleEvent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
        }

        $event->setModule($module);
    }

    /**
     * Copies the files of the module being installed over the target directory.
     */
    private function replaceModuleFiles(string $sourcePath, string $targetPath): void
    {
        $fs = new Filesystem();

        if (realpath($sourcePath) === realpath($targetPath)) {
            // The module is installed from the directory it already lives in, which is what
            // activating a dependency does. There is nothing to copy, and mirroring a
            // directory onto itself would truncate every one of its files.
            return;
        }

        try {
            // 'delete' removes what the new version no longer ships and 'override' replaces
            // files the archive dates earlier than the installed ones. The target directory
            // is no longer wiped by the deletion of the module, so without these two the
            // classes of the previous version would stay on disk, to be autoloaded and hooked.
            $fs->mirror($sourcePath, $targetPath, null, ['override' => true, 'delete' => true]);
        } catch (IOException $ioException) {
            if (!$fs->exists($targetPath)) {
                throw $ioException;
            }
        }
    }

    private function restoreActivation(int $moduleId, bool $wasActivated, EventDispatcherInterface $dispatcher): void
    {
        if (!$wasActivated) {
            return;
        }

        $toggleEvent = new ModuleToggleActivationEvent($moduleId);
        $toggleEvent->setNoCheck(true);

        $dispatcher->dispatch($toggleEvent, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
    }

    /**
     * Call the payment method of the payment module of the given order.
     *
     * A module that keeps payment methods is asked to pay with the one chosen on the cart
     * of the order, when there is one and it can still pay; with a new one otherwise.
     *
     * @throws \RuntimeException                       if no payment module can be found
     * @throws StoredMethodPaymentRateLimitedException when the customer paid with a stored method too often
     */
    public function pay(OrderPaymentEvent $event): void
    {
        $order = $event->getOrder();

        /* call pay method */
        if (null === $paymentModule = ModuleQuery::create()->findPk($order->getPaymentModuleId())) {
            throw new \RuntimeException(Translator::getInstance()->trans('Failed to find a payment Module with ID=%mid for order ID=%oid', ['%mid' => $order->getPaymentModuleId(), '%oid' => $order->getId()]));
        }

        $paymentModuleInstance = $paymentModule->getPaymentModuleInstance($this->container);

        // The instant parts have settled the whole order (and the core has turned it paid,
        // before this listener): the module that holds them has nothing left to charge,
        // and asked to pay it would turn the order paid a second time.
        if ($paymentModuleInstance instanceof InstantPaymentModuleInterface && $this->orderPaymentPartSettlement->isPaidByInstantPartsOnly($order)) {
            return;
        }

        $response = $this->askTheModuleToPay($paymentModuleInstance, $order);

        if (null !== $response && $response instanceof Response) {
            $event->setResponse($response);
        }
    }

    /**
     * @throws StoredMethodPaymentRateLimitedException
     */
    private function askTheModuleToPay(PaymentModuleInterface $paymentModule, OrderModel $order): ?Response
    {
        if (!$paymentModule instanceof PaymentModuleWithStoredMethodsInterface) {
            return $paymentModule->pay($order);
        }

        $storedMethod = $this->storedPaymentMethodPayment->methodChosenFor($order, $paymentModule);

        if (null === $storedMethod) {
            return $paymentModule->pay($order);
        }

        return $this->storedPaymentMethodPayment->pay($paymentModule, $order, $storedMethod);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(ModuleQuery::create(), $event, $dispatcher);

        $this->cacheClear($dispatcher);
    }

    protected function cacheClear(EventDispatcherInterface $dispatcher): void
    {
        $cacheEvent = new CacheEvent(
            $this->container->getParameter('kernel.cache_dir'),
        );

        $dispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::MODULE_TOGGLE_ACTIVATION => [
                ['checkToggleActivation', 255],
                ['toggleActivation', 128],
            ],
            TheliaEvents::MODULE_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::MODULE_DELETE => ['delete', 128],
            TheliaEvents::MODULE_UPDATE => ['update', 128],
            TheliaEvents::MODULE_INSTALL => ['install', 128],
            TheliaEvents::MODULE_PAY => ['pay', 128],
        ];
    }
}
