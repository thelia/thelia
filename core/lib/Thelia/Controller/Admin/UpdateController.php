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

namespace Thelia\Controller\Admin;

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductTableMap;


/**
 * Class UpdateController
 * @package Thelia\Controller\Update
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class UpdateController extends BaseAdminController
{

    protected static $version = array(
        '0' => '2.0.0-beta1',
        '1' => '2.0.0-beta2',
    );

    protected function isLatestVersion($version)
    {
        $lastEntry = end(self::$version);

        return $lastEntry == $version;
    }

    public function indexAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth(AdminResources::UPDATE, array(), AccessManager::VIEW)) {
            return $response;
        }

        $currentVersion = ConfigQuery::read('thelia_version');

        if(true === $this->isLatestVersion($currentVersion)) {
            return $this->render('update-notneeded');
        } else {
            return $this->render('update', array(
                'current_version'   => $currentVersion,
                'latest_version'    => end(self::$version)
            ));
        }
    }

    public function updateAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth(AdminResources::UPDATE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $success = true;
        $updatedVersions = array();

        $currentVersion = ConfigQuery::read('thelia_version');

        if(true === $this->isLatestVersion($currentVersion)) {
            return $this->render('update-notneeded');
        }

        $index = array_search($currentVersion, self::$version);
        $con = Propel::getServiceContainer()->getWriteConnection(ProductTableMap::DATABASE_NAME);
        $con->beginTransaction();
        $database = new Database($con->getWrappedConnection());
        try {
            for ($i = ++$index; $i < count(self::$version); $i++) {
                $this->updateToVersion(self::$version[$i], $database);
                $updatedVersions[] = self::$version[$i];
            }
            $con->commit();
        } catch(PropelException $e) {
            $con->rollBack();
            $success = false;
            $errorMsg = $e->getMessage();
        }

        if ($success) {
            return $this->render('update-success', array(
                "updated_versions" => $updatedVersions
            ));
        } else {
            return $this->render('update-fail', array(
                "error_message" => $errorMsg
            ));
        }
    }

    protected function updateToVersion($version, Database $database)
    {
        if (file_exists(THELIA_ROOT . '/install/update/'.$version.'.sql')) {
            $database->insertSql(null, array(THELIA_ROOT . '/install/update/'.$version.'.sql'));
        }

        ConfigQuery::write('thelia_version', $version);
    }
}