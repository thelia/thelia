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
 * Class ProductControllerTest
 * @package Thelia\Tests\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ProductControllerTest extends ApiTestCase
{
    /**
     * @covers \Thelia\Controller\Api\ProductController::listAction
     */
    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/products?sign='.$this->getSignParameter(""),[],[],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(10, $content, 'without parameters, the api must return 10 results');
    }

    /**
     * @covers \Thelia\Controller\Api\ProductController::listAction
     */
    public function testListActionWithLimit()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/products?limit=2&sign='.$this->getSignParameter(""),[],[],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(2, $content, 'without parameters, the api must return 10 results');
    }

    /**
     * @covers \Thelia\Controller\Api\ProductController::getAction
     */
    public function testGetAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/products/1?sign='.$this->getSignParameter(""),[],[],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content, 'without parameters, the api must return 10 results');
    }

    /**
     * @covers \Thelia\Controller\Api\ProductController::getAction
     */
    public function testGetActionWithNonExistingProduct()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/products/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(""),[],[],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }
}