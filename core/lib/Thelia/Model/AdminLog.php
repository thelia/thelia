<?php

namespace Thelia\Model;

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Log\Tlog;
use Thelia\Model\Base\AdminLog as BaseAdminLog;

class AdminLog extends BaseAdminLog
{
    /**
     * A simple helper to insert an entry in the admin log
     *
     * @param string $resource
     * @param string $action
     * @param string $message
     * @param Request       $request
     * @param UserInterface $adminUser
     * @param bool          $withRequestContent
     * @param int           $resourceId
     */
    public static function append(
        $resource,
        $action,
        $message,
        Request $request,
        UserInterface $adminUser = null,
        $withRequestContent = true,
        $resourceId = null
    ) {
        $log = new AdminLog();

        $log
            ->setAdminLogin($adminUser !== null ? $adminUser->getUsername() : '<no login>')
            ->setAdminFirstname($adminUser !== null && $adminUser instanceof Admin ? $adminUser->getFirstname() : '<no first name>')
            ->setAdminLastname($adminUser !== null && $adminUser instanceof Admin ? $adminUser->getLastname() : '<no last name>')
            ->setResource($resource)
            ->setResourceId($resourceId)
            ->setAction($action)
            ->setMessage($message)
            ->setRequest($request->toString($withRequestContent));

        try {
            $log->save();
        } catch (\Exception $ex) {
            Tlog::getInstance()->err("Failed to insert new entry in AdminLog: {ex}", array('ex' => $ex));
        }
    }
}
