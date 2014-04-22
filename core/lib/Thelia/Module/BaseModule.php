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
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Exception\ModuleException;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Map\ModuleImageTableMap;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleI18n;
use Thelia\Model\ModuleI18nQuery;
use Thelia\Model\ModuleImage;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\Image;

class BaseModule extends ContainerAware implements BaseModuleInterface
{
    const CLASSIC_MODULE_TYPE = 1;
    const DELIVERY_MODULE_TYPE = 2;
    const PAYMENT_MODULE_TYPE = 3;

    const IS_ACTIVATED = 1;
    const IS_NOT_ACTIVATED = 0;

    protected $reflected;

    protected $dispatcher = null;
    protected $request = null;

    public function activate($moduleModel = null)
    {
        if (null === $moduleModel) {
            $moduleModel = $this->getModuleModel();
        }

        if ($moduleModel->getActivate() == self::IS_NOT_ACTIVATED) {
            $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
            $con->beginTransaction();
            try {
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

    public function getDispatcher()
    {
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
                $moduleI18n = ModuleI18nQuery::create()->filterById($module->getId())->filterByLocale($locale)->findOne();
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
                if ( Image::isImage($filePath) ) {

                    $con->beginTransaction();

                    $image = new ModuleImage();
                    $image->setModuleId($module->getId());
                    $image->setPosition($imagePosition);
                    $image->save($con);

                    $imageDirectory = sprintf("%s/../../../../local/media/images/module", __DIR__);
                    $imageFileName = sprintf("%s-%d-%s", $module->getCode(), $image->getId(), $fileName);

                    $increment = 0;
                    while (file_exists($imageDirectory . '/' . $imageFileName)) {
                        $imageFileName = sprintf("%s-%d-%d-%s", $module->getCode(), $image->getId(), $increment, $fileName);
                        $increment++;
                    }

                    $imagePath = sprintf('%s/%s', $imageDirectory, $imageFileName);

                    if (! is_dir($imageDirectory)) {
                        if (! @mkdir($imageDirectory, 0777, true)) {
                            $con->rollBack();
                            throw new ModuleException(sprintf("Cannot create directory : %s", $imageDirectory), ModuleException::CODE_NOT_FOUND);
                        }
                    }

                    if (! @copy($filePath, $imagePath)) {
                        $con->rollBack();
                        throw new ModuleException(sprintf("Cannot copy file : %s to : %s", $filePath, $imagePath), ModuleException::CODE_NOT_FOUND);
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
        $moduleModel = ModuleQuery::create()->findOneByCode($this->getCode());

        if (null === $moduleModel) {
            throw new ModuleException(sprintf("Module Code `%s` not found", $this->getCode()), ModuleException::CODE_NOT_FOUND);
        }

        return $moduleModel;
    }

    public function getCode()
    {
        if (null === $this->reflected) {
            $this->reflected = new \ReflectionObject($this);
        }

        return basename(dirname($this->reflected->getFileName()));
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
        $cart = $session->getCart();

        /** @var Order $order */
        $order = $session->getOrder();

        /** @var TaxEngine $taxEngine */
        $taxEngine = $this->getContainer()->get("thelia.taxengine");

        /** @var Country $country */
        $country = $taxEngine->getDeliveryCountry();

        $amount = $with_tax ? $cart->getTaxedAmount($country, $with_discount) : $cart->getTotalAmount($with_discount);

        if ($with_postage) {
            $amount += $order->getPostage();
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
     * in the first case, your array must contains 2 indexes. The first is the compiler instance and the second the compilerPass type.
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
     * This method is called when the plugin is installed for the first time, using
     * zip upload method.
     *
     * @param ConnectionInterface $con
     */
    public function install(ConnectionInterface $con = null)
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
}
