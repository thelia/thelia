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

namespace Thelia\Install;

use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\Yaml\Yaml;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Config\DefinePropel;
use Thelia\Install\Exception\UpdateException;
use Thelia\Install\Exception\UpToDateException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductTableMap;

/**
 * Class Update
 * @package Thelia\Install
 * @author Manuel Raynaud <manu@thelia.net>
 */
class Update
{
    protected static $version = array(
        '0' => '2.0.0-beta1',
        '1' => '2.0.0-beta2',
        '2' => '2.0.0-beta3',
        '3' => '2.0.0-beta4',
        '4' => '2.0.0-RC1',
        '5' => '2.0.0',
        '6' => '2.0.1',
        '7' => '2.0.2',
        '8' => '2.0.3-beta',
        '9' => '2.0.3-beta2',
        '10' => '2.0.3',
        '11' => '2.0.4',
        '12' => '2.1.0-alpha1',
    );

    protected $updatedVersions = [];

    public function __construct($isInitialized = true)
    {
        if (false === $isInitialized) {
            $definePropel = new DefinePropel(
                new DatabaseConfiguration(),
                Yaml::parse(THELIA_CONF_DIR . 'database.yml')
            );
            $serviceContainer = Propel::getServiceContainer();
            $serviceContainer->setAdapterClass('thelia', 'mysql');
            $manager = new ConnectionManagerSingle();
            $manager->setConfiguration($definePropel->getConfig());
            $serviceContainer->setConnectionManager('thelia', $manager);
        }
    }


    public function isLatestVersion($version = null)
    {
        if (null === $version) {
            $version = $this->getCurrentVersion();
        }
        $lastEntry = end(self::$version);

        return $lastEntry == $version;
    }

    public function process()
    {
        $logger = Tlog::getInstance();
        $logger->setLevel(Tlog::DEBUG);

        $this->updatedVersions = array();

        $currentVersion = ConfigQuery::read('thelia_version');
        $logger->debug("start update process");

        if (true === $this->isLatestVersion($currentVersion)) {
            $logger->debug("You already have the latest version. No update available");
            throw new UpToDateException('You already have the latest version. No update available');
        }

        $index = array_search($currentVersion, self::$version);
        $con = Propel::getServiceContainer()->getWriteConnection(ProductTableMap::DATABASE_NAME);
        $con->beginTransaction();
        $logger->debug("begin transaction");
        $database = new Database($con->getWrappedConnection());
        $version = null;

        try {
            $size = count(self::$version);
            for ($i = ++$index; $i < $size; $i++) {
                $version = self::$version[$i];
                $this->updateToVersion($version, $database, $logger);
                $this->updatedVersions[] = $version;
            }
            $con->commit();
            $logger->debug('update successfully');
        } catch (\Exception $e) {
            $con->rollBack();
            $logger->error(sprintf('error during update process with message : %s', $e->getMessage()));

            $ex = new UpdateException($e->getMessage(), $e->getCode(), $e->getPrevious());
            $ex->setVersion($version);
            throw $ex;
        }

        $logger->debug('end of update processing');

        return $this->updatedVersions;
    }

    protected function updateToVersion($version, Database $database, Tlog $logger)
    {
        // sql update
        if (file_exists(THELIA_ROOT . '/setup/update/' . $version . '.sql')) {
            $logger->debug(sprintf('inserting file %s', $version . '.sql'));
            $database->insertSql(null, array(THELIA_ROOT . '/setup/update/' . $version . '.sql'));
            $logger->debug(sprintf('end inserting file %s', $version . '.sql'));
        }

        // php update
        if (file_exists(THELIA_ROOT . '/setup/update/' . $version . '.php')) {
            $logger->debug(sprintf('executing file %s', $version . '.php'));
            $database->insertSql(null, array(THELIA_ROOT . '/setup/update/'.$version . '.php'));
            $logger->debug(sprintf('end executing file %s', $version . '.php'));
        }

        ConfigQuery::write('thelia_version', $version);
    }

    public function getCurrentVersion()
    {
        $currentVersion = ConfigQuery::read('thelia_version');
        return $currentVersion;
    }

    public function setCurrentVersion($version)
    {
        ConfigQuery::write('thelia_version', $version);
    }

    public function getLatestVersion(){
        return end(self::$version);
    }

    public function getVersions()
    {
        return self::$version;
    }

    /**
     * @return array
     */
    public function getUpdatedVersions()
    {
        return $this->updatedVersions;
    }

    /**
     * @param array $updatedVersions
     */
    public function setUpdatedVersions($updatedVersions)
    {
        $this->updatedVersions = $updatedVersions;
    }

}
