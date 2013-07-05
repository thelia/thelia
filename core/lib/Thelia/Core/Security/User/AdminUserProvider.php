<?php
use Thelia\Core\Security\User\UserProviderInterface;
use Thelia\Model\Admin;
use Thelia\Core\Security\Encoder\PasswordEncoderInterface;

class AdminUserProvider implements UserProviderInterface {

    public function getUser($key) {

        $admin = new Admin();

        $admin = AdminQuery::create()
            ->filterByLogin($key)
            ->findOne();

        return $admin;
    }
}