<?php

/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleI18nQuery;
use Thelia\Model\Map\ModuleImageTableMap;
use Thelia\Model\ModuleI18n;
use Thelia\Tools\Image;
use Thelia\Exception\ModuleException;
use Thelia\Model\Module;
use Thelia\Model\ModuleImage;
use Thelia\Model\ModuleQuery;


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


    public function hasRequest() {
        return null !== $this->request;
    }

    public function setRequest(Request $request) {
        $this->request = $request;
    }

    public function getRequest() {
        if ($this->hasRequest() === false) {
            throw new \RuntimeException("Sorry, the request is not available in this context");
        }

        return $this->request;
    }


    public function hasDispatcher() {
        return null !== $this->dispatcher;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    public function getDispatcher() {
        if ($this->hasDispatcher() === false) {
            throw new \RuntimeException("Sorry, the dispatcher is not available in this context");
        }

        return $this->dispatcher;
    }


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

    public function deployImageFolder(Module $module, $folderPath, ConnectionInterface $con = null)
    {
        try {
            $directoryBrowser = new \DirectoryIterator($folderPath);
        } catch (\UnexpectedValueException $e) {
            throw $e;
        }
        if (null === $con) {
            $con = \Propel\Runtime\Propel::getConnection(
                ModuleImageTableMap::DATABASE_NAME
            );
        }

        /* browse the directory */
        $imagePosition = 1;
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

    public function install(ConnectionInterface $con = null)
    {
        // Implement this method to do something useful.
    }

    public function preActivation(ConnectionInterface $con = null)
    {
        return true;
    }

    public function postActivation(ConnectionInterface $con = null)
    {
        // Implement this method to do something useful.
    }

    public function preDeactivation(ConnectionInterface $con = null)
    {
        return true;
    }

    public function postDeactivation(ConnectionInterface $con = null)
    {
        // Implement this method to do something useful.
    }

    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        // Implement this method to do something useful.
    }
}