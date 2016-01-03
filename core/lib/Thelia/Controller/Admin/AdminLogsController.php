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

namespace Thelia\Controller\Admin;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminLogQuery;

class AdminLogsController extends BaseAdminController
{
    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::ADMIN_LOG, array(), AccessManager::VIEW)) {
            return $response;
        }

        // Render the edition template.
        return $this->render('admin-logs');
    }

    public function loadLoggerAjaxAction()
    {
        $entries = array();

        /** @var AdminLog $entry */
        foreach (AdminLogQuery::getEntries(
            $this->getRequest()->request->get('admins', array()),
            $this->getRequest()->request->get('fromDate', null),
            $this->getRequest()->request->get('toDate', null),
            array_merge($this->getRequest()->request->get('resources', array()), $this->getRequest()->request->get('modules', array())),
            null
        ) as $entry) {
            $entries[] = array(
                "head" => sprintf(
                    "%s|%s|%s:%s%s",
                    date('Y-m-d H:i:s', $entry->getCreatedAt()->getTimestamp()),
                    $entry->getAdminLogin(),
                    $entry->getResource(),
                    $entry->getAction(),
                    (null !== $entry->getResourceId()) ? ":" . $entry->getResourceId() : ""
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
