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
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Install\Exception\UpToDateException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductTableMap;

/**
 * Class Update
 * @package Thelia\Install
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
        '12' => '2.0.5',
    );

    protected function isLatestVersion($version)
    {
        $lastEntry = end(self::$version);

        return $lastEntry == $version;
    }

    public function process()
    {

        $logger = Tlog::getInstance();
        $logger->setLevel(Tlog::DEBUG);

        $updatedVersions = array();

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
        try {
            $size = count(self::$version);
            for ($i = ++$index; $i < $size; $i++) {
                $this->updateToVersion(self::$version[$i], $database, $logger);
                $updatedVersions[] = self::$version[$i];
            }
            $con->commit();
            $logger->debug('update successfully');
        } catch (PropelException $e) {
            $con->rollBack();
            $logger->error(sprintf('error during update process with message : %s', $e->getMessage()));
            throw $e;
        }

        $logger->debug('end of update processing');

        return $updatedVersions;
    }

    protected function updateToVersion($version, Database $database,Tlog $logger)
    {
        if (file_exists(THELIA_ROOT . '/setup/update/'.$version.'.sql')) {
            $logger->debug(sprintf('inserting file %s', $version.'$sql'));
            $database->insertSql(null, array(THELIA_ROOT . '/setup/update/'.$version.'.sql'));
            $logger->debug(sprintf('end inserting file %s', $version.'$sql'));
        }

        ConfigQuery::write('thelia_version', $version);
    }
}
