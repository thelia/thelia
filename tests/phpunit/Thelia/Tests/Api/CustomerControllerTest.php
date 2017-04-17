<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Api;

use Thelia\Model\CustomerQuery;
use Thelia\Tests\ApiTestCase;

/**
 * Class CustomerControllerTest
 * @package Thelia\Tests\Api
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Baptiste Cabarrou <bcabarrou@openstudio.fr>
 */
class CustomerControllerTest extends ApiTestCase
{
    /**
     * @covers \Thelia\Controller\Api\CustomerController::listAction
     */
    public function testListActionWithDefaultParameters()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/customers?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(10, $content);

        $this->customerKeyTest($content[0]);
    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::listAction
     */
    public function testListActionWithOrderError()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/customers?order=foo&sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(500, $client->getResponse()->getStatusCode(), 'Http status code must be 500');
    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::listAction
     */
    public function testListActionWithLimit()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/customers?limit=1&sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content);

        $this->customerKeyTest($content[0]);
    }

    protected function customerKeyTest($customer)
    {
        $this->assertArrayHasKey('ID', $customer, 'customer entity must contains Id key');
        $this->assertArrayHasKey('REF', $customer, 'customer entity must contains Ref key');
        $this->assertArrayHasKey('TITLE', $customer, 'customer entity must contains TitleId key');
        $this->assertArrayHasKey('FIRSTNAME', $customer, 'customer entity must contains Firstname key');
        $this->assertArrayHasKey('LASTNAME', $customer, 'customer entity must contains Lastname key');
        $this->assertArrayHasKey('EMAIL', $customer, 'customer entity must contains Email key');
        $this->assertArrayHasKey('RESELLER', $customer, 'customer entity must contains Reseller key');
        $this->assertArrayHasKey('SPONSOR', $customer, 'customer entity must contains Sponsor key');
        $this->assertArrayHasKey('DISCOUNT', $customer, 'customer entity must contains Discount key');
        $this->assertArrayHasKey('CREATE_DATE', $customer, 'customer entity must contains CreatedAt key');
        $this->assertArrayHasKey('UPDATE_DATE', $customer, 'customer entity must contains UpdatedAt key');
    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::getAction
     */
    public function testGetAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/customers/1?&sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content);

        $this->customerKeyTest($content[0]);
    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::getAction
     */
    public function testGetActionWithUnexistingCustomer()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/customers/'.PHP_INT_MAX.'?&sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }

    public function testCreate()
    {
        $user = [
            'title' => 1,
            'firstname' => 'Thelia',
            'lastname'  => 'Thelia',
            'address1'  => 'street address 1',
            'city'      => 'Clermont-Ferrand',
            'zipcode'   => 63100,
            'country'   => 64,
            'email'     => sprintf("%s@thelia.fr", uniqid()),
            'password'  => 'azerty',
            'lang_id'   => 1
        ];

        $requestContent = json_encode($user);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'POST',
            '/api/customers?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode());

        $content = json_decode($client->getResponse()->getContent(), true);

        return $content[0]['ID'];
    }

    public function testCreateWithExistingEmail()
    {
        $customer = CustomerQuery::create()->addAscendingOrderByColumn('RAND()')->findOne();

        $user = [
            'title' => 1,
            'firstname' => 'Thelia',
            'lastname'  => 'Thelia',
            'address1'  => 'street address 1',
            'city'      => 'Clermont-Ferrand',
            'zipcode'   => 63100,
            'country'   => 64,
            'email'     => $customer->getEmail(),
            'password'  => 'azerty',
            'lang_id'   => 1
        ];

        $requestContent = json_encode($user);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'POST',
            '/api/customers?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(500, $client->getResponse()->getStatusCode());
    }

    /**
     * @depends testCreate
     */
    public function testDelete($customerId)
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/api/customers/'.$customerId.'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(204, $client->getResponse()->getStatusCode(), "the response code must be 204");
    }

    public function testDeleteWithExistingOrders()
    {
        $client = static::createClient();
        $client->request(
            'DELETE',
            '/api/customers/1?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(403, $client->getResponse()->getStatusCode(), "the response code must be 403 because the customer already have orders");
    }

    public function testUpdateCustomer()
    {
        $user = [
            'id' => 1,
            'title' => 1,
            'firstname' => 'Thelia',
            'lastname'  => 'Thelia',
            'address1'  => 'street address 1',
            'city'      => 'Clermont-Ferrand',
            'zipcode'   => 63100,
            'country'   => 64,
            'email'     => sprintf("%s@thelia.fr", uniqid()),
            'lang_id'   => 1
        ];

        $requestContent = json_encode($user);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'PUT',
            '/api/customers?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode());
    }

    public function testUpdateCustomerWithUnexistingCustomer()
    {
        $user = [
            'id' => PHP_INT_MAX,
            'title' => 1,
            'firstname' => 'Thelia',
            'lastname'  => 'Thelia',
            'address1'  => 'street address 1',
            'city'      => 'Clermont-Ferrand',
            'zipcode'   => 63100,
            'country'   => 64,
            'email'     => sprintf("%s@thelia.fr", uniqid()),
            'lang_id'   => 1
        ];

        $requestContent = json_encode($user);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'PUT',
            '/api/customers?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(500, $client->getResponse()->getStatusCode());
    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::checkLoginAction
     */
    public function testCheckLogin()
    {
        $logins = [
            'email'    => CustomerQuery::create()->findPk(1)->getEmail(),
            'password' => 'azerty'
        ];

        $requestContent = json_encode($logins);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'POST',
            '/api/customers/checkLogin?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::checkLoginAction
     */
    public function testCheckLoginWithUnexistingEmail()
    {
        $logins = [
            'email'    => 'test@exemple.com',
            'password' => 'azerty'
        ];

        $requestContent = json_encode($logins);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'POST',
            '/api/customers/checkLogin?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::checkLoginAction
     */
    public function testCheckLoginWithWrongPassword()
    {
        $logins = [
            'email'    => CustomerQuery::create()->findPk(1)->getEmail(),
            'password' => 'notthis'
        ];

        $requestContent = json_encode($logins);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'POST',
            '/api/customers/checkLogin?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }
}
