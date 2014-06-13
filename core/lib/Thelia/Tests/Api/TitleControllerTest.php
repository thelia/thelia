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
 * Class TitleControllerTest
 * @package Thelia\Tests\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class TitleControllerTest extends ApiTestCase
{
    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title?sign='.$this->getSignParameter(''), [], [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(3, $content, 'reponse must contains 3 results');
    }

    public function testListActionWithLocale()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title?lang=fr_FR&sign='.$this->getSignParameter(''), [], [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(3, $content, 'response must contains 3 results');

        $firstResult = $content[0];

        $this->assertEquals('fr_FR', $firstResult['LOCALE'], 'the returned locale must be fr_FR');
    }

    public function testGetAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title/1?sign='.$this->getSignParameter(''), [], [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content, 'response must contains only 1 result');

    }

    public function testGetActionWithUnexistingId()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(''), [], [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
        $content = json_decode($client->getResponse()->getContent(), true);
    }
}