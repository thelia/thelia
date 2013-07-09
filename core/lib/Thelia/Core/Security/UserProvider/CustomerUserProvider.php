<?php
namespace Thelia\Core\Security\User\UserProvider;


class CustomerUserProvider implements UserProviderInterface {

    public function getUser($key) {

        $customer = new Customer();

        $customer = CustomerQuery::create()
            ->filterByEmail($key)
            ->findOne();

        return $customer;
    }
}