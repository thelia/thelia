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

use Thelia\Model\AttributeAvQuery;
use Thelia\Tests\ApiTestCase;

/**
 * Class AttributeAvControllerTest
 * @package Thelia\Tests\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class AttributeAvControllerTest extends ApiTestCase
{
    public function testListAction()
    {
        $client = static::createClient();

        $attributeAvCount = AttributeAvQuery::create()->count();
        if ($attributeAvCount > 10) {
            $attributeAvCount = 10;
        }

        $client->request(
            'GET',
            '/api/attribute-avs?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Response must be 200 on category list action');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount($attributeAvCount, $content, "10 results must be return by default");
    }

    public function testListWithTranslation()
    {
        $client = static::createClient();

        $attributeAvCount = AttributeAvQuery::create()->count();
        if ($attributeAvCount > 10) {
            $attributeAvCount = 10;
        }

        $client->request(
            'GET',
            '/api/attribute-avs?lang=fr_FR&sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Response must be 200 on category list action');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount($attributeAvCount, $content, "10 results must be return by default");

        $firstResult = $content[0];
        $this->assertEquals(1, $firstResult['IS_TRANSLATED'], 'content must be translated');
        $this->assertEquals('fr_FR', $firstResult['LOCALE'], 'the locale must be fr_FR');
    }

    public function testGetAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/attribute-avs/1?sign='.$this->getSignParameter(''),
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
            '/api/attribute-avs/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Response must be 200 on category list action');
    }
}
