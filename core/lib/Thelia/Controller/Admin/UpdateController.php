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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ConfigQuery;


/**
 * Class UpdateController
 * @package Thelia\Controller\Update
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class UpdateController extends BaseAdminController
{

    const UPDATE_RESOURCE = 'update';

    protected static $version = array(
        '0' => '2.0.0-beta1',
        '1' => '2.0.0-beta2',
    );

    protected function isLatestVersion($version)
    {
        $lastEntry = array_pop(self::$version);

        return $lastEntry == $version;
    }

    public function indexAction()
    {
        // Check current user authorization
        if (null !==  $this->checkAuth(AdminResources::UPDATE, array(), AccessManager::VIEW)) {
            throw new NotFoundHttpException();
        }

        $currentVersion = ConfigQuery::read('thelia_version');

        if(true === $this->isLatestVersion($currentVersion)) {
            return $this->render('update-notneeded');
        } else {
            return $this->render('update-index', array(
                'current_version' => $currentVersion
            ));
        }
    }
}