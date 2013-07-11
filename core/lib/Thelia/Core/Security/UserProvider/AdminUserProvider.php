<?php
namespace Thelia\Core\Security\User\UserProvider;

class AdminUserProvider implements UserProviderInterface {

    public function getUser($key) {

        $admin = new Admin();

        $admin = AdminQuery::create()
            ->filterByLogin($key)
            ->findOne();

        return $admin;
    }
}