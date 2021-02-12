<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminLogQuery;

class AdminLogsController extends BaseAdminController
{
    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::ADMIN_LOG, [], AccessManager::VIEW)) {
            return $response;
        }

        // Render the edition template.
        return $this->render('admin-logs');
    }

    public function loadLoggerAjaxAction()
    {
        $entries = [];

        /** @var AdminLog $entry */
        foreach (AdminLogQuery::getEntries(
            $this->getRequest()->request->get('admins', []),
            $this->getRequest()->request->get('fromDate', null),
            $this->getRequest()->request->get('toDate', null),
            array_merge($this->getRequest()->request->get('resources', []), $this->getRequest()->request->get('modules', [])),
            null
        ) as $entry) {
            $entries[] = [
                'head' => sprintf(
                    '%s|%s|%s:%s%s',
                    date('Y-m-d H:i:s', $entry->getCreatedAt()->getTimestamp()),
                    $entry->getAdminLogin(),
                    $entry->getResource(),
                    $entry->getAction(),
                    (null !== $entry->getResourceId()) ? ':'.$entry->getResourceId() : ''
                ),
                'data' => $entry->getMessage(),
            ];
        }

        return $this->render(
            'ajax/logger',
            [
                'entries' => $entries,
            ]
        );
    }
}
