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
 * Class CountryControllerTest
 * @package Thelia\Tests\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CountryControllerTest extends ApiTestCase
{
    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/countries?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThan(0, count($content), 'The request must return at least one result');
    }

    public function testListActionWithLocale()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/countries?lang=fr_FR&sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThan(0, count($content), 'The request must return at least one result');

        $firstResult = $content[0];

        $this->assertEquals('fr_FR', $firstResult['LOCALE'], 'the returned locale must be fr_FR');
    }

    public function testGetAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/countries/1?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(1, $content, 'The request must return one result');
    }

    public function testGetActionWithLocale()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/countries/1?lang=fr_FR&sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(1, $content, 'The request must return one result');

        $firstResult = $content[0];

        $this->assertEquals('fr_FR', $firstResult['LOCALE'], 'the returned locale must be fr_FR');
    }
}
