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

namespace Thelia\Model;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Log\Tlog;
use Thelia\Model\Base\AdminLog as BaseAdminLog;

class AdminLog extends BaseAdminLog
{
    /**
     * A simple helper to insert an entry in the admin log.
     *
     * @param string $resource
     * @param string $action
     * @param string $message
     * @param bool   $withRequestContent
     * @param int    $resourceId
     */
    public static function append(
        $resource,
        $action,
        $message,
        Request $request,
        ?UserInterface $adminUser = null,
        $withRequestContent = true,
        $resourceId = null,
    ): void {
        $log = new self();

        $log
            ->setAdminLogin($adminUser instanceof UserInterface ? $adminUser->getUsername() : '<no login>')
            ->setAdminFirstname($adminUser instanceof UserInterface && $adminUser instanceof Admin ? $adminUser->getFirstname() : '<no first name>')
            ->setAdminLastname($adminUser instanceof UserInterface && $adminUser instanceof Admin ? $adminUser->getLastname() : '<no last name>')
            ->setResource($resource)
            ->setResourceId($resourceId)
            ->setAction($action)
            ->setMessage($message)
            ->setRequest($request->toString($withRequestContent));

        try {
            $log->save();
        } catch (\Exception $exception) {
            Tlog::getInstance()->err('Failed to insert new entry in AdminLog: {ex}', ['ex' => $exception]);
        }
    }
}
