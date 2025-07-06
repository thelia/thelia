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
namespace Thelia\Module;

use Exception;
use RuntimeException;
use DirectoryIterator;
use Propel\Runtime\Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;
use Thelia\Model\Hook;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Hook\HookCreateAllEvent;
use Thelia\Core\Event\Hook\HookUpdateEvent;
use Thelia\Core\Event\Order\OrderPayTotalEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\ModuleException;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\HookQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\ModuleImageTableMap;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleI18n;
use Thelia\Model\ModuleI18nQuery;
use Thelia\Model\ModuleImage;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\Image;

class BaseModule implements BaseModuleInterface
{
    protected ?ContainerInterface $container = null;

    public const CLASSIC_MODULE_TYPE = 1;

    public const DELIVERY_MODULE_TYPE = 2;

    public const PAYMENT_MODULE_TYPE = 3;

    public const MODULE_CATEGORIES = 'classic,delivery,payment,marketplace,price,accounting,seo,administration,statistic';

    public const IS_ACTIVATED = 1;

    public const IS_NOT_ACTIVATED = 0;

    public const IS_MANDATORY = 1;

    public const IS_NOT_MANDATORY = 0;

    public const IS_HIDDEN = 1;

    public const IS_NOT_HIDDEN = 0;

    protected $reflected;

    protected $dispatcher;

    protected $request;

    // Do no use this attribute directly, use getModuleModel() instead.
    private $moduleModel;

    /**
     * @param Module $moduleModel
     *
     * @throws PropelException
     * @throws Throwable
     */
    public function activate($moduleModel = null): void
    {
        if (null === $moduleModel) {
            $moduleModel = $this->getModuleModel();
        }

        if ($moduleModel->getActivate() === self::IS_NOT_ACTIVATED) {
            $con = Propel::getConnection(ModuleTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                if (!$this->preActivation($con)) {
                    throw new Exception('An error occured during the module pre activation.');
                }

                $moduleModel->setActivate(self::IS_ACTIVATED);
                $moduleModel->save();

                $this->initializeCoreI18n();
                $cacheEvent = new CacheEvent(
                    $this->getContainer()?->getParameter('kernel.cache_dir')
                );
                $this->getDispatcher()->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);

                $this->postActivation($con);
                $con->commit();
            } catch (Exception $e) {
                $con->rollBack();
                $moduleModel->setActivate(self::IS_NOT_ACTIVATED);
                $moduleModel->save();
                throw $e;
            }

            $this->registerHooks();
        }
    }

    public function deActivate($moduleModel = null): void
    {
        if (null === $moduleModel) {
            $moduleModel = $this->getModuleModel();
        }

        if ($moduleModel->getActivate() == self::IS_ACTIVATED) {
            $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
            $con->beginTransaction();
            try {
                if ($this->preDeactivation($con)) {
                    $moduleModel->setActivate(self::IS_NOT_ACTIVATED);
                    $moduleModel->save($con);
                    $this->postDeactivation($con);

                    $con->commit();
                }
            } catch (Exception $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    public function hasContainer(): bool
    {
        return null !== $this->container;
    }

    public function getContainer(): ContainerInterface
    {
        if ($this->hasContainer() === false) {
            throw new RuntimeException('Sorry, container is not available in this context');
        }

        return $this->container;
    }

    public function hasRequest(): bool
    {
        return null !== $this->request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @throws RuntimeException
     */
    public function getRequest(): Request
    {
        if ($this->hasRequest() === false) {
            // Try to get request from container.
            $this->setRequest($this->getContainer()->get('request_stack')?->getCurrentRequest());
        }

        if ($this->hasRequest() === false) {
            throw new RuntimeException('Sorry, the request is not available in this context');
        }

        return $this->request;
    }

    public function hasDispatcher(): bool
    {
        return null !== $this->dispatcher;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @throws RuntimeException
     *
     * @return EventDispatcherInterface
     */
    public function getDispatcher()
    {
        if ($this->hasDispatcher() === false) {
            // Try to get dispatcher from container.
            $this->setDispatcher($this->getContainer()->get('event_dispatcher'));
        }

        if ($this->hasDispatcher() === false) {
            throw new RuntimeException('Sorry, the dispatcher is not available in this context');
        }

        return $this->dispatcher;
    }

    public function setTitle(Module $module, $titles): void
    {
        if (\is_array($titles)) {
            foreach ($titles as $locale => $title) {
                $moduleI18n = ModuleI18nQuery::create()
                    ->filterById($module->getId())->filterByLocale($locale)
                    ->findOne();

                if (null === $moduleI18n) {
                    $moduleI18n = new ModuleI18n();
                    $moduleI18n
                        ->setId($module->getId())
                        ->setLocale($locale)
                        ->setTitle($title)
                    ;
                    $moduleI18n->save();
                } else {
                    $moduleI18n->setTitle($title);
                    $moduleI18n->save();
                }
            }
        }
    }

    public static function getConfigValue($variableName, $defaultValue = null, $valueLocale = null)
    {
        return ModuleConfigQuery::create()
            ->getConfigValue(self::getModuleId(), $variableName, $defaultValue, $valueLocale);
    }

    public static function setConfigValue($variableName, $variableValue, $valueLocale = null, $createIfNotExists = true): void
    {
        ModuleConfigQuery::create()
            ->setConfigValue(self::getModuleId(), $variableName, $variableValue, $valueLocale, $createIfNotExists);
    }

    public function deployImageFolder(Module $module, $folderPath, ConnectionInterface $con = null): void
    {
        $directoryBrowser = new DirectoryIterator($folderPath);

        if (!$con instanceof ConnectionInterface) {
            $con = Propel::getConnection(
                ModuleImageTableMap::DATABASE_NAME
            );
        }

        /* browse the directory */
        $imagePosition = 1;
        /** @var DirectoryIterator $directoryContent */
        foreach ($directoryBrowser as $directoryContent) {
            /* is it a file ? */
            if ($directoryContent->isFile()) {
                $fileName = $directoryContent->getFilename();
                $filePath = $directoryContent->getPathName();

                /* is it a picture ? */
                if (Image::isImage($filePath)) {
                    $con->beginTransaction();

                    $image = new ModuleImage();
                    $image->setModuleId($module->getId());
                    $image->setPosition($imagePosition);
                    $image->save($con);

                    $imageDirectory = sprintf('%s/media/images/module', THELIA_LOCAL_DIR);
                    $imageFileName = sprintf('%s-%d-%s', $module->getCode(), $image->getId(), $fileName);

                    $increment = 0;
                    while (file_exists($imageDirectory.'/'.$imageFileName)) {
                        $imageFileName = sprintf(
                            '%s-%d-%d-%s',
                            $module->getCode(),
                            $image->getId(),
                            $increment,
                            $fileName
                        );
                        ++$increment;
                    }

                    $imagePath = sprintf('%s/%s', $imageDirectory, $imageFileName);

                    if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0777, true) && !is_dir($imageDirectory)) {
                        $con->rollBack();
                        throw new ModuleException(
                            sprintf('Cannot create directory : %s', $imageDirectory),
                            ModuleException::CODE_NOT_FOUND
                        );
                    }

                    if (!@copy($filePath, $imagePath)) {
                        $con->rollBack();
                        throw new ModuleException(
                            sprintf('Cannot copy file : %s to : %s', $filePath, $imagePath),
                            ModuleException::CODE_NOT_FOUND
                        );
                    }

                    $image->setFile($imageFileName);
                    $image->save($con);

                    $con->commit();
                    ++$imagePosition;
                }
            }
        }
    }

    public function getModuleModel()
    {
        if (null === $this->moduleModel) {
            $this->moduleModel = ModuleQuery::create()->findOneByCode($this->getCode());

            if (null === $this->moduleModel) {
                throw new ModuleException(
                    sprintf('Module Code `%s` not found', $this->getCode()),
                    ModuleException::CODE_NOT_FOUND
                );
            }
        }

        return $this->moduleModel;
    }

    /**
     * Module A may use static method from module B, thus we have to cache
     * a couple (module code => module id).
     *
     * @return int The module id, in a static way, with a cache
     */
    private static array $moduleIds = [];

    public static function getModuleId()
    {
        $code = self::getModuleCode();

        if (!isset(self::$moduleIds[$code])) {
            if (null === $module = ModuleQuery::create()->findOneByCode($code)) {
                throw new ModuleException(
                    sprintf('Module Code `%s` not found', $code),
                    ModuleException::CODE_NOT_FOUND
                );
            }

            self::$moduleIds[$code] = $module->getId();
        }

        return self::$moduleIds[$code];
    }

    public static function getModuleCode(): string
    {
        $fullClassName = explode('\\', static::class);

        return end($fullClassName);
    }

    /*
     * The module code
     */
    public function getCode(): string
    {
        return self::getModuleCode();
    }

    /**
     * Check if this module is the payment module for a given order.
     *
     * @param Order $order an order
     *
     * @return bool true if this module is the payment module for the given order
     */
    public function isPaymentModuleFor(Order $order): bool
    {
        $model = $this->getModuleModel();

        return $order->getPaymentModuleId() == $model->getId();
    }

    /**
     * Check if this module is the delivery module for a given order.
     *
     * @param Order $order an order
     *
     * @return bool true if this module is the delivery module for the given order
     */
    public function isDeliveryModuleFor(Order $order): bool
    {
        $model = $this->getModuleModel();

        return $order->getDeliveryModuleId() == $model->getId();
    }

    /**
     * Use this method to process the total order just before payment. (ex: gift card, discount, credit).
     * Call on modules type 3  "AbstractPaymentModule pay()" to ensure that all discounts are taken into account.
     */
    public function getOrderPayTotalAmount(
        Order $order,
        float|int &$tax = 0,
        bool $includeDiscount = true,
        bool $includePostage = true
    ): float|int {
        $orderPayEvent = new OrderPayTotalEvent($order);
        $orderPayEvent
            ->setTax($tax)
            ->setIncludePostage($includePostage)
            ->setIncludeDiscount($includeDiscount);

        $this->getDispatcher()->dispatch($orderPayEvent, TheliaEvents::ORDER_PAY_GET_TOTAL);

        $tax = $orderPayEvent->getTax();

        return $orderPayEvent->getTotal();
    }

    /**
     * A convenient method to get the current order total, with or without tax, discount or postage.
     * This method operates on the order currently in the user's session, and should not be used to
     * get the total amount of an order already stored in the database. For such orders, use
     * Order::getTotalAmount() method.
     *
     * @param bool $with_tax      if true, to total price will include tax amount
     * @param bool $with_discount if true, the total price will include discount, if any
     * @param bool $with_postage  if true, the total price will include the delivery costs, if any
     *
     * @return float|int the current order amount
     */
    public function getCurrentOrderTotalAmount($with_tax = true, $with_discount = true, $with_postage = true)
    {
        /** @var Session $session */
        $session = $this->getRequest()->getSession();

        /** @var Cart $cart */
        $cart = $session->getSessionCart($this->getDispatcher());

        /** @var Order $order */
        $order = $session->getOrder();

        /** @var TaxEngine $taxEngine */
        $taxEngine = $this->getContainer()->get('thelia.taxEngine');

        /** @var Country $country */
        $country = $taxEngine->getDeliveryCountry();

        $state = $taxEngine->getDeliveryState();

        $amount = $with_tax ? $cart->getTaxedAmount($country, $with_discount, $state) : $cart->getTotalAmount($with_discount, $country, $state);

        if ($with_postage) {
            if ($with_tax) {
                $amount += $order->getPostage();
            } else {
                $amount += $order->getPostage() - $order->getPostageTax();
            }
        }

        return $amount;
    }

    public static function getCompilers(): array
    {
        return [];
    }

    public static function configureContainer(ContainerConfigurator $containerConfigurator): void
    {
        // Override this method to configure the container for your module
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        // Override this method to configure the services for your module
    }

    public static function loadConfiguration(ContainerBuilder $containerBuilder): void
    {
        // Override this method load more configuration for your module
    }

    /**
     * @deprecated use getRoutePrefix instead
     */
    public static function getAnnotationRoutePrefix(): string
    {
        // Override to add a prefix to all your module annotated routes
        return '';
    }

    public static function getRoutePrefix(): string
    {
        return '';
    }

    public function install(ConnectionInterface $con = null): void
    {
        // Override this method to do something useful.
    }

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        // Override this method to do something useful.
    }

    public function preActivation(ConnectionInterface $con = null): bool
    {
        // Override this method to do something useful.
        return true;
    }

    public function postActivation(ConnectionInterface $con = null): void
    {
        // Override this method to do something useful.
    }

    public function preDeactivation(ConnectionInterface $con = null): bool
    {
        // Override this method to do something useful.
        return true;
    }

    public function postDeactivation(ConnectionInterface $con = null): void
    {
        // Override this method to do something useful.
    }

    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        // Override this method to do something useful.
    }

    public function getHooks(): array
    {
        return [];
    }

    public function registerHooks(): void
    {
        $moduleHooks = $this->getHooks();

        if ($moduleHooks !== []) {
            $allowedTypes = (array) TemplateDefinition::getStandardTemplatesSubdirsIterator();
            $defaultLang = Lang::getDefaultLanguage();
            $defaultLocale = $defaultLang->getLocale();

            /**
             * @var EventDispatcherInterface $dispatcher
             */
            $dispatcher = $this->container->get('event_dispatcher');

            foreach ($moduleHooks as $hook) {
                $isValid = \is_array($hook)
                    && isset($hook['type'])
                    && \array_key_exists($hook['type'], $allowedTypes)
                    && isset($hook['code'])
                    && \is_string($hook['code'])
                    && (isset($hook['code']) && ($hook['code'] !== '' && $hook['code'] !== '0'))
                ;

                if (!$isValid) {
                    Tlog::getInstance()->notice('The module '.$this->getCode().' tried to register an invalid hook');

                    continue;
                }

                /**
                 * Create or update hook db entry.
                 *
                 * @var Hook $hookModel
                 */
                [$hookModel, $updateData] = $this->createOrUpdateHook($hook, $dispatcher, $defaultLocale);

                /**
                 * Update translations.
                 */
                $event = new HookUpdateEvent($hookModel->getId());

                foreach ($updateData as $locale => $data) {
                    $event
                        ->setCode($hookModel->getCode())
                        ->setNative($hookModel->getNative())
                        ->setByModule($hookModel->getByModule())
                        ->setActive($hookModel->getActivate())
                        ->setBlock($hookModel->getBlock())
                        ->setNative($hookModel->getNative())
                        ->setType($hookModel->getType())
                        ->setLocale($locale)
                        ->setChapo($data['chapo'])
                        ->setTitle($data['title'])
                        ->setDescription($data['description'])
                    ;

                    $dispatcher->dispatch($event, TheliaEvents::HOOK_UPDATE);
                }
            }
        }
    }

    protected function createOrUpdateHook(array $hook, EventDispatcherInterface $dispatcher, $defaultLocale): array
    {
        $hookModel = HookQuery::create()->filterByCode($hook['code'])->findOne();

        if ($hookModel === null) {
            $event = new HookCreateAllEvent();
        } else {
            $event = new HookUpdateEvent($hookModel->getId());
        }

        /**
         * Get used I18n variables.
         */
        $locale = $defaultLocale;

        [$titles, $descriptions, $chapos] = $this->getHookI18nInfo($hook, $defaultLocale);

        /*
         * If the default locale exists
         * extract it to save it in create action
         *
         * otherwise take the first
         */
        if (isset($titles[$defaultLocale])) {
            $title = $titles[$defaultLocale];

            unset($titles[$defaultLocale]);
        } else {
            $locale = array_key_first($titles);
            $title = array_shift($titles);
        }

        $description = $this->arrayKeyPop($locale, $descriptions);
        $chapo = $this->arrayKeyPop($locale, $chapos);

        /*
         * Set data
         */
        $event
            ->setBlock(isset($hook['block']) && (bool) $hook['block'])
            ->setLocale($locale)
            ->setTitle($title)
            ->setDescription($description)
            ->setChapo($chapo)
            ->setType($hook['type'])
            ->setCode($hook['code'])
            ->setNative(false)
            ->setByModule(isset($hook['module']) && (bool) $hook['module'])
            ->setActive(isset($hook['active']) && (bool) $hook['active'])
        ;

        /*
         * Dispatch the event
         */
        $dispatcher->dispatch(
            $event,

            $hookModel === null ?
                TheliaEvents::HOOK_CREATE_ALL :
                TheliaEvents::HOOK_UPDATE
        );

        return [
            $event->getHook(),
            $this->formatHookDataForI18n($titles, $descriptions, $chapos),
        ];
    }

    /**
     * @return array{title: mixed, description: mixed, chapo: mixed}[]
     */
    protected function formatHookDataForI18n(array $titles, array $descriptions, array $chapos): array
    {
        $locales = array_merge(
            array_keys($titles),
            array_keys($descriptions),
            array_keys($chapos)
        );

        $locales = array_unique($locales);

        $data = [];

        foreach ($locales as $locale) {
            $data[$locale] = [
                'title' => $titles[$locale] ?? null,
                'description' => $descriptions[$locale] ?? null,
                'chapo' => $chapos[$locale] ?? null,
            ];
        }

        return $data;
    }

    protected function getHookI18nInfo(array $hook, $defaultLocale): array
    {
        $titles = [];
        $descriptions = [];
        $chapos = [];

        /*
         * Get the defined titles
         */
        if (isset($hook['title'])) {
            $titles = $this->extractI18nValues($hook['title'], $defaultLocale);
        }

        /*
         * Then the defined descriptions
         */
        if (isset($hook['description'])) {
            $descriptions = $this->extractI18nValues($hook['description'], $defaultLocale);
        }

        /*
         * Then the short descriptions
         */
        if (isset($hook['chapo'])) {
            $chapos = $this->extractI18nValues($hook['chapo'], $defaultLocale);
        }

        return [$titles, $descriptions, $chapos];
    }

    /**
     * @return mixed[]
     */
    protected function extractI18nValues($data, $defaultLocale): array
    {
        $returnData = [];

        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                if (!\is_string($key)) {
                    continue;
                }

                $returnData[$key] = $value;
            }
        } elseif (\is_scalar($data)) {
            $returnData[$defaultLocale] = $data;
        }

        return $returnData;
    }

    protected function arrayKeyPop($key, array &$array)
    {
        $value = null;

        if (\array_key_exists($key, $array)) {
            $value = $array[$key];

            unset($array[$key]);
        }

        return $value;
    }

    /**
     * @since 2.4
     */
    protected function getPropelSchemaDir(): string
    {
        return $this->getModuleDir().DS.'Config'.DS.'schema.xml';
    }

    /**
     * @since 2.4
     */
    protected function hasPropelSchema(): bool
    {
        return (new Filesystem())->exists($this->getPropelSchemaDir());
    }

    /**
     * Add core translations of the module to use in `preActivation` and `postActivation`
     * when the module is not yest activated and translations are not available.
     */
    private function initializeCoreI18n(): void
    {
        if ($this->hasContainer()) {
            /** @var Translator $translator */
            $translator = $this->container->get('thelia.translator');

            if (null !== $translator) {
                $i18nPath = sprintf('%s/I18n/', $this->getModuleDir());
                $languages = LangQuery::create()->find();

                foreach ($languages as $language) {
                    $locale = $language->getLocale();
                    $i18nFile = sprintf('%s%s.php', $i18nPath, $locale);

                    if (is_file($i18nFile) && is_readable($i18nFile)) {
                        $translator->addResource('php', $i18nFile, $locale, strtolower(self::getModuleCode()));
                    }
                }
            }
        }
    }

    public function getModuleDir(): string
    {
        return is_dir(THELIA_MODULE_DIR.$this->getCode())
            ? THELIA_MODULE_DIR.$this->getCode()
            : THELIA_LOCAL_MODULE_DIR.$this->getCode();
    }

    public function setContainer(?ContainerInterface $container = null): static
    {
        $this->container = $container;

        return $this;
    }
}
