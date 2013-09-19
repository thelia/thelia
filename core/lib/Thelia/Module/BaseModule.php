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

use Symfony\Component\DependencyInjection\ContainerAware;
use Thelia\Model\Map\ModuleImageTableMap;
use Thelia\Tools\Image;
use Thelia\Exception\ModuleException;
use Thelia\Model\Module;
use Thelia\Model\ModuleImage;
use Thelia\Model\ModuleQuery;

abstract class BaseModule extends ContainerAware
{
    const CLASSIC_MODULE_TYPE = 1;
    const DELIVERY_MODULE_TYPE = 2;
    const PAYMENT_MODULE_TYPE = 3;

    const IS_ACTIVATED = 1;
    const IS_NOT_ACTIVATED = 0;

    public function __construct()
    {

    }

    public function activate()
    {
        $moduleModel = $this->getModuleModel();
        if($moduleModel->getActivate() == self::IS_NOT_ACTIVATED) {
            $moduleModel->setActivate(self::IS_ACTIVATED);
            $moduleModel->save();
            try {
                $this->afterActivation();
            } catch(\Exception $e) {
                $moduleModel->setActivate(self::IS_NOT_ACTIVATED);
                $moduleModel->save();
                throw $e;
            }
        }
    }

    public function hasContainer()
    {
        return null === $this->container;
    }

    public function getContainer()
    {
        if ($this->hasContainer() === false) {
            throw new \RuntimeException("Sorry, container his not available in this context");
        }

        return $this->container;
    }

    public function deployImageFolder(Module $module, $folderPath)
    {
        try {
            $directoryBrowser = new \DirectoryIterator($folderPath);
        } catch(\UnexpectedValueException $e) {
            throw $e;
        }

        $con = \Propel\Runtime\Propel::getConnection(
            ModuleImageTableMap::DATABASE_NAME
        );

        /* browse the directory */
        $imagePosition = 1;
        foreach($directoryBrowser as $directoryContent) {
            /* is it a file ? */
            if ($directoryContent->isFile()) {

                $fileName = $directoryContent->getFilename();
                $filePath = $directoryContent->getPathName();

                /* is it a picture ? */
                if( Image::isImage($filePath) ) {

                    $con->beginTransaction();

                    $image = new ModuleImage();
                    $image->setModuleId($module->getId());
                    $image->setPosition($imagePosition);
                    $image->save($con);

                    $imageDirectory = sprintf("%s/../../../../local/media/images/module", __DIR__);
                    $imageFileName = sprintf("%s-%d-%s", $module->getCode(), $image->getId(), $fileName);

                    $increment = 0;
                    while(file_exists($imageDirectory . '/' . $imageFileName)) {
                        $imageFileName = sprintf("%s-%d-%d-%s", $module->getCode(), $image->getId(), $increment, $fileName);
                        $increment++;
                    }

                    $imagePath = sprintf('%s/%s', $imageDirectory, $imageFileName);

                    if (! is_dir($imageDirectory)) {
                        if(! @mkdir($imageDirectory, 0777, true)) {
                            $con->rollBack();
                            throw new ModuleException(sprintf("Cannot create directory : %s", $imageDirectory), ModuleException::CODE_NOT_FOUND);
                        }
                    }

                    if(! @copy($filePath, $imagePath)) {
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

        if(null === $moduleModel) {
            throw new ModuleException(sprintf("Module Code `%s` not found", $this->getCode()), ModuleException::CODE_NOT_FOUND);
        }

        return $moduleModel;
    }

    abstract public function getCode();
    abstract public function install();
    abstract public function afterActivation();
    abstract public function destroy();

}
