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
 * Class ProductImageControllerTest
 * @package Thelia\Tests\Api
 * @author manuel raynaud <manu@thelia.net>
 */
class ProductImageControllerTest extends ApiTestCase
{
    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/products/1/images?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThan(0, count($content), 'must contain at least 1 image');
    }

    public function testListActionWithNonExistingProduct()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/products/'.PHP_INT_MAX.'/images?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }

    public function testGetAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/products/1/images/1?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content, 'image get action must retrieve 1 image');
    }

    public function testGetActionWithNonExistingImage()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/products/1/images/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }
}
