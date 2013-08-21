<?php
namespace Thelia\Core\Security\UserProvider;

use Thelia\Action\Customer;
use Thelia\Model\CustomerQuery;
class CustomerUserProvider implements UserProviderInterface
{
    public function getUser($key)
    {
        $customer = CustomerQuery::create()
            ->filterByEmail($key)
            ->findOne();

        return $customer;
    }
}
