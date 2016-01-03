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

use Thelia\Model\CountryQuery;
use Thelia\Model\Map\CountryTableMap;
use Thelia\Model\Map\TaxTableMap;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Tests\ApiTestCase;

/**
 * Class TaxRuleControllerTest
 * @package Thelia\Tests\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author manuel raynaud <manu@raynaud.io>
 */
class TaxRuleControllerTest extends ApiTestCase
{
    protected static $defaultId;

    public static function setUpBeforeClass()
    {
        $taxRule = TaxRuleQuery::create()
            ->filterByIsDefault(1)
            ->findOne();

        self::$defaultId = $taxRule->getId();
    }

    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/tax-rules?sign='.$this->getSignParameter(''),
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
            '/api/tax-rules?lang=fr_FR&sign='.$this->getSignParameter(''),
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
            '/api/tax-rules/1?sign='.$this->getSignParameter(''),
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
            '/api/tax-rules/1?lang=fr_FR&sign='.$this->getSignParameter(''),
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

    public function testCreateTaxRule()
    {
        $this->markTestSkipped('Must be revisited. Tax Rules do not work like this.');

        $taxes = TaxQuery::create()
            ->limit(2)
            ->select(TaxTableMap::ID)
            ->find()
            ->toArray()
        ;

        $countries = CountryQuery::create()
            ->limit(2)
            ->select(CountryTableMap::ID)
            ->find()
            ->toArray()
        ;

        $data = [
            "country" => $countries,
            "tax" => $taxes,
            "i18n" => array(
                [
                    "locale" => "en_US",
                    "title" => "Test tax rule",
                    "description" => "foo",
                ],
                [
                    "locale" => "fr_FR",
                    "title" => "Test règle de taxe",
                ]
            )
        ];

        $requestContent = json_encode($data);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'POST',
            '/api/tax-rules?lang=en_US&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $response = $client->getResponse();

        $this->assertEquals(201, $response->getStatusCode());

        $content = json_decode($response->getContent(), true)[0];

        $this->assertEquals("Test tax rule", $content["TITLE"]);
        $this->assertEquals(0, $content["IS_DEFAULT"]);

        return $content["ID"];
    }

    /**
     * @param $taxRuleId
     * @depends testCreateTaxRule
     */
    public function testUpdateTaxRule($taxRuleId)
    {
        $this->markTestSkipped('Must be revisited. Tax Rules do not work like this.');

        $data = [
            "id" => $taxRuleId,
            "default" => true,
            "i18n" => array(
                [
                    "locale" => "en_US",
                    "description" => "bar",
                ],
                [
                    "locale" => "fr_FR",
                    "description" => "baz",
                ]
            )
        ];

        $requestContent = json_encode($data);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'PUT',
            '/api/tax-rules?lang=fr_FR&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $response = $client->getResponse();

        $this->assertEquals(201, $response->getStatusCode());

        $content = json_decode($response->getContent(), true)[0];

        $this->assertEquals("Test règle de taxe", $content["TITLE"]);
        $this->assertEquals("baz", $content["DESCRIPTION"]);
        $this->assertEquals(1, $content["IS_DEFAULT"]);

        return $content["ID"];
    }

    /**
     * @param $taxRuleId
     * @depends testCreateTaxRule
     */
    public function testDeleteTaxRule($taxRuleId)
    {
        $this->markTestSkipped('Must be revisited. Tax Rules do not work like this.');

        $client = static::createClient();
        $servers = $this->getServerParameters();

        $client->request(
            'DELETE',
            '/api/tax-rules/'.$taxRuleId.'?lang=fr_FR&sign='.$this->getSignParameter(''),
            [],
            [],
            $servers
        );

        $response = $client->getResponse();

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testCreateTaxRuleWithInvalidData()
    {
        $this->markTestSkipped('Must be revisited. Tax Rules do not work like this.');

        $countries = CountryQuery::create()
            ->limit(2)
            ->select(CountryTableMap::ID)
            ->find()
            ->toArray()
        ;

        $data = [
            "country" => $countries,
            "tax" => array(),
            "i18n" => array(
                [
                    "locale" => "en_US",
                    "title" => "Test tax rule",
                    "description" => "foo",
                ],
                [
                    "locale" => "fr_FR",
                    "title" => "Test règle de taxe",
                ]
            )
        ];

        $requestContent = json_encode($data);

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'POST',
            '/api/tax-rules?lang=en_US&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $response = $client->getResponse();

        $this->assertEquals(500, $response->getStatusCode());
    }

    public static function tearDownAfterClass()
    {
        TaxRuleQuery::create()
            ->filterById(self::$defaultId)
            ->update(array('IsDefault' => true));
    }
}
