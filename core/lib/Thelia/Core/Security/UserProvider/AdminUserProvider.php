<?php
namespace Thelia\Core\Security\UserProvider;

use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;

class AdminUserProvider implements UserProviderInterface {

    public function getUser($key) {

        $admin = AdminQuery::create()
            ->filterByLogin($key)
            ->findOne();

        return $admin;
    }
}