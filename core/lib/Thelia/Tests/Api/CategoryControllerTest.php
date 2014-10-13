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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\CategoryQuery;
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
            '/api/categories?sign='.$this->getSignParameter(''),
            [],
            [],
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
            '/api/categories?lang=fr_FR&sign='.$this->getSignParameter(''),
            [],
            [],
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
            '/api/categories/1?sign='.$this->getSignParameter(''),
            [],
            [],
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
            '/api/categories/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Response must be 200 on category list action');
    }

    public function testCreate()
    {
        $category = [
            'thelia_category_creation' => [
                'title' => 'test en',
                'locale' => 'en_US',
                'visible' => 1,
                'parent' => 0
            ]
        ];

        $requestContent = json_encode($category);
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client = static::createClient();

        $client->request(
            'POST',
            '/api/categories?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode(), 'Http status code must be 201');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('en_US', $content[0]['LOCALE']);
    }

    public function testCreateFr()
    {
        $category = [
            'thelia_category_creation' => [
                'title' => 'test fr',
                'locale' => 'fr_FR',
                'visible' => 1,
                'parent' => 0
            ]
        ];

        $requestContent = json_encode($category);
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client = static::createClient();

        $client->request(
            'POST',
            '/api/categories?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode(), 'Http status code must be 201');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('fr_FR', $content[0]['LOCALE']);
    }

    public function testUpdate()
    {
        $category = CategoryQuery::create()
            ->orderById(Criteria::DESC)
            ->findOne();

        $content = [
            'thelia_category_modification' => [
                'title' => 'foo',
                'parent' => 0,
                'locale' => 'en_US',
                'visible' => 1,
                'chapo' => 'cat chapo',
                'description' => 'cat description',
                'postscriptum' => 'cat postscriptum'
            ]
        ];
        $requestContent = json_encode($content);
        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'PUT',
            '/api/categories/'.$category->getId().'?sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(204, $client->getResponse()->getStatusCode(), 'HTTP status code must be 204');
    }

    public function testDeleteAction()
    {
        $category = CategoryQuery::create()
            ->orderById(Criteria::DESC)
            ->findOne();

        $client = static::createClient();

        $client->request(
            'DELETE',
            '/api/categories/'.$category->getId().'?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(204, $client->getResponse()->getStatusCode(), 'HTTP status code muse be 204');
    }
}
