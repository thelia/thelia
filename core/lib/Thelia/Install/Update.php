<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
        '3' => '2.0.0-beta4'
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

        $success = true;
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
            for ($i = ++$index; $i < count(self::$version); $i++) {
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
        if (file_exists(THELIA_ROOT . '/install/update/'.$version.'.sql')) {
            $logger->debug(sprintf('inserting file %s', $version.'$sql'));
            $database->insertSql(null, array(THELIA_ROOT . '/install/update/'.$version.'.sql'));
            $logger->debug(sprintf('end inserting file %s', $version.'$sql'));
        }

        ConfigQuery::write('thelia_version', $version);
    }
}
