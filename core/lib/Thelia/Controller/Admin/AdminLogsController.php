<?php

declare(strict_types=1);

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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminLogQuery;

class AdminLogsController extends BaseAdminController
{
    public function defaultAction()
    {
        if (($response = $this->checkAuth(AdminResources::ADMIN_LOG, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        // Render the edition template.
        return $this->render('admin-logs');
    }

    public function loadLoggerAjaxAction(): Response
    {
        $entries = [];

        /** @var AdminLog $entry */
        foreach (AdminLogQuery::getEntries(
            $this->getRequest()->request->get('admins', []),
            $this->getRequest()->request->get('fromDate'),
            $this->getRequest()->request->get('toDate'),
            array_merge((array) $this->getRequest()->request->get('resources', []), (array) $this->getRequest()->request->get('modules', [])),
        ) as $entry) {
            $entries[] = [
                'head' => \sprintf(
                    '%s|%s|%s:%s%s',
                    date('Y-m-d H:i:s', $entry->getCreatedAt()->getTimestamp()),
                    $entry->getAdminLogin(),
                    $entry->getResource(),
                    $entry->getAction(),
                    (null !== $entry->getResourceId()) ? ':'.$entry->getResourceId() : '',
                ),
                'data' => $entry->getMessage(),
            ];
        }

        return $this->render(
            'ajax/logger',
            [
                'entries' => $entries,
            ],
        );
    }
}
