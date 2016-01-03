<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Event\Hook\HookCreateAllEvent;
use Thelia\Core\Event\Hook\HookUpdateEvent;
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

class BaseModule extends ContainerAware implements BaseModuleInterface
{
    const CLASSIC_MODULE_TYPE  = 1;
    const DELIVERY_MODULE_TYPE = 2;
    const PAYMENT_MODULE_TYPE  = 3;

    const MODULE_CATEGORIES = 'classic,delivery,payment,marketplace,price,accounting,seo,administration,statistic';

    const IS_ACTIVATED     = 1;
    const IS_NOT_ACTIVATED = 0;

    protected $reflected;

    protected $dispatcher = null;
    protected $request = null;

    // Do no use this attribute directly, use getModuleModel() instead.
    private $moduleModel = null;

    public function activate($moduleModel = null)
    {
        if (null === $moduleModel) {
            $moduleModel = $this->getModuleModel();
        }

        if ($moduleModel->getActivate() == self::IS_NOT_ACTIVATED) {
            $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
            $con->beginTransaction();
            try {
                $this->initializeCoreI18n();
                if ($this->preActivation($con)) {
                    $moduleModel->setActivate(self::IS_ACTIVATED);
                    $moduleModel->save($con);
                    $this->postActivation($con);
                    $con->commit();
                }
            } catch (\Exception $e) {
                $con->rollBack();
                throw $e;
            }

            $this->registerHooks();
        }
    }

    public function deActivate($moduleModel = null)
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
            } catch (\Exception $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    public function hasContainer()
    {
        return null !== $this->container;
    }

    public function getContainer()
    {
        if ($this->hasContainer() === false) {
            throw new \RuntimeException("Sorry, container is not available in this context");
        }

        return $this->container;
    }

    public function hasRequest()
    {
        return null !== $this->request;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Request the request.
     *
     * @throws \RuntimeException
     */
    public function getRequest()
    {
        if ($this->hasRequest() === false) {
            // Try to get request from container.
            $this->setRequest($this->getContainer()->get('request'));
        }

        if ($this->hasRequest() === false) {
            throw new \RuntimeException("Sorry, the request is not available in this context");
        }

        return $this->request;
    }

    public function hasDispatcher()
    {
        return null !== $this->dispatcher;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return EventDispatcherInterface
     * @throws \RuntimeException
     */
    public function getDispatcher()
    {
        if ($this->hasDispatcher() === false) {
            // Try to get dispatcher from container.
            $this->setDispatcher($this->getContainer()->get('event_dispatcher'));
        }

        if ($this->hasDispatcher() === false) {
            throw new \RuntimeException("Sorry, the dispatcher is not available in this context");
        }

        return $this->dispatcher;
    }

    /**
     * Sets a module titles for various languages
     *
     * @param Module $module the module.
     * @param array  $titles an associative array of locale => title_string
     */
    public function setTitle(Module $module, $titles)
    {
        if (is_array($titles)) {
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

    /**
     * Get a module's configuration variable
     *
     * @param  string $variableName the variable name
     * @param  string $defaultValue the default value, if variable is not defined
     * @param  null   $valueLocale  the required locale, or null to get default one
     * @return string the variable value
     */
    public static function getConfigValue($variableName, $defaultValue = null, $valueLocale = null)
    {
        return ModuleConfigQuery::create()
            ->getConfigValue(self::getModuleId(), $variableName, $defaultValue, $valueLocale);
    }

    /**
     * Set module configuration variable, creating it if required
     *
     * @param  string          $variableName      the variable name
     * @param  string          $variableValue     the variable value
     * @param  null            $valueLocale       the locale, or null if not required
     * @param  bool            $createIfNotExists if true, the variable will be created if not already defined
     * @throws \LogicException if variable does not exists and $createIfNotExists is false
     * @return $this;
     */
    public static function setConfigValue($variableName, $variableValue, $valueLocale = null, $createIfNotExists = true)
    {
        ModuleConfigQuery::create()
            ->setConfigValue(self::getModuleId(), $variableName, $variableValue, $valueLocale, $createIfNotExists);
    }

    /**
     * Ensure the proper deployment of the module's images.
     *
     * TODO : this method does not take care of internationalization. This is a bug.
     *
     * @param Module              $module     the module
     * @param string              $folderPath the image folder path
     * @param ConnectionInterface $con
     *
     * @throws \Thelia\Exception\ModuleException
     * @throws \Exception
     * @throws \UnexpectedValueException
     */
    public function deployImageFolder(Module $module, $folderPath, ConnectionInterface $con = null)
    {
        try {
            $directoryBrowser = new \DirectoryIterator($folderPath);
        } catch (\UnexpectedValueException $e) {
            throw $e;
        }
        if (null === $con) {
            $con = Propel::getConnection(
                ModuleImageTableMap::DATABASE_NAME
            );
        }

        /* browse the directory */
        $imagePosition = 1;
        /** @var \DirectoryIterator $directoryContent */
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

                    $imageDirectory = sprintf("%s/media/images/module", THELIA_LOCAL_DIR);
                    $imageFileName = sprintf("%s-%d-%s", $module->getCode(), $image->getId(), $fileName);

                    $increment = 0;
                    while (file_exists($imageDirectory . '/' . $imageFileName)) {
                        $imageFileName = sprintf(
                            "%s-%d-%d-%s",
                            $module->getCode(),
                            $image->getId(),
                            $increment,
                            $fileName
                        );
                        $increment++;
                    }

                    $imagePath = sprintf('%s/%s', $imageDirectory, $imageFileName);

                    if (! is_dir($imageDirectory)) {
                        if (! @mkdir($imageDirectory, 0777, true)) {
                            $con->rollBack();
                            throw new ModuleException(
                                sprintf("Cannot create directory : %s", $imageDirectory),
                                ModuleException::CODE_NOT_FOUND
                            );
                        }
                    }

                    if (! @copy($filePath, $imagePath)) {
                        $con->rollBack();
                        throw new ModuleException(
                            sprintf("Cannot copy file : %s to : %s", $filePath, $imagePath),
                            ModuleException::CODE_NOT_FOUND
                        );
                    }

                    $image->setFile($imageFileName);
                    $image->save($con);

                    $con->commit();
                    $imagePosition++;
                }
            }
        }
    }

    /**
     * @return Module
     * @throws \Thelia\Exception\ModuleException
     */
    public function getModuleModel()
    {
        if (null === $this->moduleModel) {
            $this->moduleModel = ModuleQuery::create()->findOneByCode($this->getCode());

            if (null === $this->moduleModel) {
                throw new ModuleException(
                    sprintf("Module Code `%s` not found", $this->getCode()),
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
    private static $moduleIds = [];

    public static function getModuleId()
    {
        $code = self::getModuleCode();

        if (! isset(self::$moduleIds[$code])) {
            if (null === $module = ModuleQuery::create()->findOneByCode($code)) {
                throw new ModuleException(
                    sprintf("Module Code `%s` not found", $code),
                    ModuleException::CODE_NOT_FOUND
                );
            }

            self::$moduleIds[$code] = $module->getId();
        }

        return self::$moduleIds[$code];
    }

    /**
     * @return string The module code, in a static wayord
     */
    public static function getModuleCode()
    {
        $fullClassName = explode('\\', get_called_class());

        return end($fullClassName);
    }

    /*
     * The module code
     */
    public function getCode()
    {
        return self::getModuleCode();
    }

    /**
     * Check if this module is the payment module for a given order
     *
     * @param  Order $order an order
     * @return bool  true if this module is the payment module for the given order.
     */
    public function isPaymentModuleFor(Order $order)
    {
        $model = $this->getModuleModel();

        return $order->getPaymentModuleId() == $model->getId();
    }

    /**
     * Check if this module is the delivery module for a given order
     *
     * @param  Order $order an order
     * @return bool  true if this module is the delivery module for the given order.
     */
    public function isDeliveryModuleFor(Order $order)
    {
        $model = $this->getModuleModel();

        return $order->getDeliveryModuleId() == $model->getId();
    }

    /**
     * A convenient method to get the current order total, with or without tax, discount or postage.
     * This method operates on the order currently in the user's session, and should not be used to
     * get the total amount of an order already stored in the database. For such orders, use
     * Order::getTotalAmount() method.
     *
     * @param bool $with_tax      if true, to total price will include tax amount
     * @param bool $with_discount if true, the total price will include discount, if any
     * @param bool $with_postage  if true, the total price will include the delivery costs, if any.
     *
     * @return float|int the current order amount.
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
        $taxEngine = $this->getContainer()->get("thelia.taxengine");

        /** @var Country $country */
        $country = $taxEngine->getDeliveryCountry();

        $amount = $with_tax ? $cart->getTaxedAmount($country, $with_discount) : $cart->getTotalAmount($with_discount);

        if ($with_postage) {
            if ($with_tax) {
                $amount += $order->getPostage();
            } else {
                $amount += $order->getPostage() - $order->getPostageTax();
            }
        }

        return $amount;
    }

    /**
     *
     * This method adds new compilers to Thelia container
     *
     * You must return an array. This array can contain :
     *  - arrays
     *  - one or many instance(s) of \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface
     *
     * in the first case, your array must contains 2 indexes.
     * The first is the compiler instance and the second the compilerPass type.
     * Example :
     * return array(
     *  array(
     *    new \MyModule\DependencyInjection\Compiler\MySuperCompilerPass(),
     *    \Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_OPTIMIZATION
     *  )
     * );
     *
     * In the seconde case, just an instance of CompilerPassInterface.
     * Example :
     * return array (
     *  new \MyModule\DependencyInjection\Compiler\MySuperCompilerPass()
     * );
     *
     * But you can combine both behaviors
     * Example :
     *
     * return array(
     *  new \MyModule\DependencyInjection\Compiler\MySuperCompilerPass(),
     *  array(
     *      new \MyModule\DependencyInjection\Compiler\MyOtherSuperCompilerPass(),
     *      Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_OPTIMIZATION
     *  )
     * );
     *
     */
    public static function getCompilers()
    {
        return array();
    }

    /**
     * This method is called when the plugin is installed for the first time
     *
     * @param ConnectionInterface $con
     */
    public function install(ConnectionInterface $con = null)
    {
        // Override this method to do something useful.
    }

    /**
     * This method is called when a newer version of the plugin is installed
     *
     * @param string $currentVersion the current (installed) module version, as defined in the module.xml file
     * @param string $newVersion the new module version, as defined in the module.xml file
     * @param ConnectionInterface $con
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null)
    {
        // Override this method to do something useful.
    }

    /**
     * This method is called before the module activation, and may prevent it by returning false.
     *
     * @param ConnectionInterface $con
     *
     * @return bool true to continue module activation, false to prevent it.
     */
    public function preActivation(ConnectionInterface $con = null)
    {
        // Override this method to do something useful.
        return true;
    }

    /**
     * This method is called just after the module was successfully activated.
     *
     * @param ConnectionInterface $con
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        // Override this method to do something useful.
    }

    /**
     * This method is called before the module de-activation, and may prevent it by returning false.
     *
     * @param  ConnectionInterface $con
     * @return bool                true to continue module de-activation, false to prevent it.
     */
    public function preDeactivation(ConnectionInterface $con = null)
    {
        // Override this method to do something useful.
        return true;
    }

    public function postDeactivation(ConnectionInterface $con = null)
    {
        // Override this method to do something useful.
    }

    /**
     * This method is called just before the deletion of the module, giving the module an opportunity
     * to delete its data.
     *
     * @param ConnectionInterface $con
     * @param bool                $deleteModuleData if true, the module should remove all its data from the system.
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        // Override this method to do something useful.
    }

    /**
     * @return array
     *
     * This method must be used when your module defines hooks.
     * Override this and return an array of your hooks names to register them
     *
     * This returned value must be like the example, only type and code are mandatory
     *
     * Example:
     *
     * return array(
     *
     *      // Only register the title in the default language
     *      array(
     *          "type" => TemplateDefinition::BACK_OFFICE,
     *          "code" => "my_super_hook_name",
     *          "title" => "My hook",
     *          "description" => "My hook is really, really great",
     *      ),
     *
     *      // Manage i18n
     *      array(
     *          "type" => TemplateDefinition::FRONT_OFFICE,
     *          "code" => "my_hook_name",
     *          "title" => array(
     *              "fr_FR" => "Mon Hook",
     *              "en_US" => "My hook",
     *          ),
     *          "description" => array(
     *              "fr_FR" => "Mon hook est vraiment super",
     *              "en_US" => "My hook is really, really great",
     *          ),
     *          "chapo" => array(
     *              "fr_FR" => "Mon hook est vraiment super",
     *              "en_US" => "My hook is really, really great",
     *          ),
     *          "block" => true,
     *          "active" => true
     *      )
     * );
     */
    public function getHooks()
    {
        return array();
    }

    public function registerHooks()
    {
        $moduleHooks = $this->getHooks();

        if (is_array($moduleHooks) && !empty($moduleHooks)) {
            $allowedTypes = (array) TemplateDefinition::getStandardTemplatesSubdirsIterator();
            $defaultLang = Lang::getDefaultLanguage();
            $defaultLocale = $defaultLang->getLocale();

            /**
             * @var EventDispatcherInterface $dispatcher
             */
            $dispatcher = $this->container->get("event_dispatcher");

            foreach ($moduleHooks as $hook) {
                $isValid = is_array($hook) &&
                    isset($hook["type"]) &&
                    array_key_exists($hook["type"], $allowedTypes) &&
                    isset($hook["code"]) &&
                    is_string($hook["code"]) &&
                    !empty($hook["code"])
                ;

                if (!$isValid) {
                    Tlog::getInstance()->notice("The module ".$this->getCode()." tried to register an invalid hook");

                    continue;
                }

                /**
                 * Create or update hook db entry.
                 *
                 * @var \Thelia\Model\Hook $hookModel
                 */
                list($hookModel, $updateData) = $this->createOrUpdateHook($hook, $dispatcher, $defaultLocale);

                /**
                 * Update translations
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
                        ->setChapo($data["chapo"])
                        ->setTitle($data["title"])
                        ->setDescription($data["description"])
                    ;

                    $dispatcher->dispatch(TheliaEvents::HOOK_UPDATE, $event);
                }
            }
        }
    }

    protected function createOrUpdateHook(array $hook, EventDispatcherInterface $dispatcher, $defaultLocale)
    {
        $hookModel = HookQuery::create()->filterByCode($hook["code"])->findOne();

        if ($hookModel === null) {
            $event = new HookCreateAllEvent();
        } else {
            $event = new HookUpdateEvent($hookModel->getId());
        }

        /**
         * Get used I18n variables
         */
        $locale = $defaultLocale;

        list($titles, $descriptions, $chapos) = $this->getHookI18nInfo($hook, $defaultLocale);

        /**
         * If the default locale exists
         * extract it to save it in create action
         *
         * otherwise take the first
         */
        if (isset($titles[$defaultLocale])) {
            $title = $titles[$defaultLocale];

            unset($titles[$defaultLocale]);
        } else {
            reset($titles);

            $locale = key($titles);
            $title = array_shift($titles);
        }

        $description = $this->arrayKeyPop($locale, $descriptions);
        $chapo = $this->arrayKeyPop($locale, $chapos);

        /**
         * Set data
         */
        $event
            ->setBlock(isset($hook["block"]) && (bool) $hook["block"])
            ->setLocale($locale)
            ->setTitle($title)
            ->setDescription($description)
            ->setChapo($chapo)
            ->setType($hook["type"])
            ->setCode($hook["code"])
            ->setNative(false)
            ->setByModule(isset($hook["module"]) && (bool) $hook["module"])
            ->setActive(isset($hook["active"]) && (bool) $hook["active"])
        ;

        /**
         * Dispatch the event
         */
        $dispatcher->dispatch(
            (
            $hookModel === null ?
                TheliaEvents::HOOK_CREATE_ALL :
                TheliaEvents::HOOK_UPDATE
            ),
            $event
        );

        return [
            $event->getHook(),
            $this->formatHookDataForI18n($titles, $descriptions, $chapos)
        ];
    }

    protected function formatHookDataForI18n(array $titles, array $descriptions, array $chapos)
    {
        $locales = array_merge(
            array_keys($titles),
            array_keys($descriptions),
            array_keys($chapos)
        );

        $locales = array_unique($locales);

        $data = array();

        foreach ($locales as $locale) {
            $data[$locale] = [
                'title' => !isset($titles[$locale]) ? null : $titles[$locale],
                'description' => !isset($descriptions[$locale]) ? null: $descriptions[$locale],
                'chapo' => !isset($chapos[$locale]) ? null : $chapos[$locale]
            ];
        }

        return $data;
    }

    protected function getHookI18nInfo(array $hook, $defaultLocale)
    {
        $titles = array();
        $descriptions = array();
        $chapos = array();

        /**
         * Get the defined titles
         */
        if (isset($hook["title"])) {
            $titles = $this->extractI18nValues($hook["title"], $defaultLocale);
        }

        /**
         * Then the defined descriptions
         */
        if (isset($hook["description"])) {
            $descriptions = $this->extractI18nValues($hook["description"], $defaultLocale);
        }

        /**
         * Then the short descriptions
         */
        if (isset($hook["chapo"])) {
            $chapos = $this->extractI18nValues($hook["chapo"], $defaultLocale);
        }

        return [$titles, $descriptions, $chapos];
    }

    protected function extractI18nValues($data, $defaultLocale)
    {
        $returnData = array();

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $returnData[$key] = $value;
            }
        } elseif (is_scalar($data)) {
            $returnData[$defaultLocale] = $data;
        }

        return $returnData;
    }

    protected function arrayKeyPop($key, array &$array)
    {
        $value = null;

        if (array_key_exists($key, $array)) {
            $value = $array[$key];

            unset($array[$key]);
        }

        return $value;
    }

    /**
     * Add core translations of the module to use in `preActivation` and `postActivation`
     * when the module is not yest activated and translations are not available
     */
    private function initializeCoreI18n()
    {
        if ($this->hasContainer()) {
            /** @var Translator $translator */
            $translator = $this->container->get('thelia.translator');

            if (null !== $translator) {
                $i18nPath = sprintf('%s%s/I18n/', THELIA_MODULE_DIR, $this->getCode());
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
}
