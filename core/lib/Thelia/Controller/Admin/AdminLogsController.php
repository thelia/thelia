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

use Thelia\Core\Security\AccessManager;
use Thelia\Model\AdminLogQuery;

class AdminLogsController extends BaseAdminController
{
    const RESOURCE_CODE = "admin.admin-logs";

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, AccessManager::VIEW)) return $response;

        // Render the edition template.
        return $this->render('admin-logs');
    }

    public function loadLoggerAjaxAction()
    {
        $entries = array();

        foreach( AdminLogQuery::getEntries(
                    $this->getRequest()->request->get('admins', array()),
                    $this->getRequest()->request->get('fromDate', null),
                    $this->getRequest()->request->get('toDate', null),
                    array_merge($this->getRequest()->request->get('resources', array()), $this->getRequest()->request->get('modules', array())),
                    null
                ) as $entry) {

            $entries[] = array(
                "head" => sprintf(
                    "[%s][%s][%s:%s]",
                    date('Y-m-d H:i:s', $entry->getCreatedAt()->getTimestamp()),
                    $entry->getAdminLogin(),
                    $entry->getResource(),
                    $entry->getAction()
                ),
                "data" => $entry->getMessage(),
            );
        }

        return $this->render(
            'ajax/logger',
            array(
                'entries' => $entries,
            )
        );
    }
}
