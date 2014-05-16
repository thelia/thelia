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

use Thelia\Tests\ApiTestCase;


/**
 * Class CustomerControllerTest
 * @package Thelia\Tests\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
            '/api/customers?sign='.$this->getSignParameter(""),[],[],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(10, $content);

    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::listAction
     */
    public function testListActionWithOrderError()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/customers?order=foo&sign='.$this->getSignParameter(""),[],[],
            $this->getServerParameters()
        );

        $this->assertEquals(400, $client->getResponse()->getStatusCode(), 'Http status code must be 400');

    }

    /**
     * @covers \Thelia\Controller\Api\CustomerController::listAction
     */
    public function testListActionWithLimit()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/customers?limit=1&sign='.$this->getSignParameter(""),[],[],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content);

    }


}