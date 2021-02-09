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

namespace Carousel;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Thelia\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Module\BaseModule;

/**
 * Class Carousel
 * @package Carousel
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Carousel extends BaseModule
{
    const DOMAIN_NAME = 'carousel';

    public function preActivation(ConnectionInterface $con = null)
    {
        if (! self::getConfigValue('is_initialized', false)) {
            $database = new Database($con);

            $database->insertSql(null, [__DIR__ . '/Config/thelia.sql']);

            self::setConfigValue('is_initialized', true);
        }

        return true;
    }

    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        $database = new Database($con);

        $database->insertSql(null, [__DIR__ . '/Config/sql/destroy.sql']);
    }

    public function getUploadDir()
    {
        $uploadDir = ConfigQuery::read('images_library_path');

        if ($uploadDir === null) {
            $uploadDir = THELIA_LOCAL_DIR . 'media' . DS . 'images';
        } else {
            $uploadDir = THELIA_ROOT . $uploadDir;
        }

        return $uploadDir . DS . self::DOMAIN_NAME;
    }

    /**
     * @param string $currentVersion
     * @param string $newVersion
     * @param ConnectionInterface $con
     * @author Thomas Arnaud <tarnaud@openstudio.fr>
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null)
    {
        $uploadDir = $this->getUploadDir();
        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($uploadDir) && $fileSystem->exists(__DIR__ . DS . 'media' . DS . 'carousel')) {
            $finder = new Finder();
            $finder->files()->in(__DIR__ . DS . 'media' . DS . 'carousel');

            $fileSystem->mkdir($uploadDir);

            /** @var SplFileInfo $file */
            foreach ($finder as $file) {
                copy($file, $uploadDir . DS . $file->getRelativePathname());
            }
            $fileSystem->remove(__DIR__ . DS . 'media');
        }

        $finder = (new Finder())->files()->name('#.*?\.sql#')->sortByName()->in(__DIR__ . DS . 'Config' . DS .'update');

        if (0 === $finder->count()) {
            return;
        }

        $database = new Database($con);

        // apply update only if table exists
        if ($database->execute("SHOW TABLES LIKE 'carousel'")->rowCount() === 0) {
            return;
        }

        /** @var SplFileInfo $updateSQLFile */
        foreach ($finder as $updateSQLFile) {
            if (version_compare($currentVersion, str_replace('.sql', '', $updateSQLFile->getFilename()), '<')) {
                $database->insertSql(null, [$updateSQLFile->getPathname()]);
            }
        }
    }

    /**
     * Defines how services are loaded in your modules
     *
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator)
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
