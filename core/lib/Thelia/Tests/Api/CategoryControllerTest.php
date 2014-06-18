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
 * Class CategoryControllerTest
 * @package Thelia\Tests\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CategoryControllerTest extends ApiTestCase
{
    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/categories?sign='.$this->getSignParameter(''), [],[],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Response must be 200 on category list action');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(10, $content, "10 results must be return by default");
    }

    public function testListWithTranslation()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/categories?lang=fr_FR&sign='.$this->getSignParameter(''), [],[],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Response must be 200 on category list action');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(10, $content, "10 results must be return by default");

        $firstResult = $content[0];
        $this->assertEquals(1, $firstResult['IS_TRANSLATED'], 'content must be translated');
        $this->assertEquals('fr_FR', $firstResult['LOCALE'], 'the locale must be fr_FR');
    }

    public function testGetAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/categories/1?sign='.$this->getSignParameter(''), [],[],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Response must be 200 on category list action');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content, "10 results must be return by default");
    }

    public function testGetActionWithWrongId()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/categories/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(''), [],[],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Response must be 200 on category list action');
    }
}