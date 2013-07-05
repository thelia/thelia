<?php
use Thelia\Core\Security\User\UserProviderInterface;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Core\Security\UserNotFoundException;
use Thelia\Core\Security\Encoder\PasswordEncoderInterface;

class CustomerUserProvider implements UserProviderInterface {

    public function getUser($key) {

        $customer = new Customer();

        $customer = CustomerQuery::create()
            ->filterByEmail($key)
            ->findOne();

        return $customer;
    }
}