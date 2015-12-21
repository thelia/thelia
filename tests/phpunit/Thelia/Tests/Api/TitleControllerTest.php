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

use Thelia\Model\CustomerTitleQuery;
use Thelia\Tests\ApiTestCase;

/**
 * Class TitleControllerTest
 * @package Thelia\Tests\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TitleControllerTest extends ApiTestCase
{
    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $maxCount = CustomerTitleQuery::create()->count();

        if ($maxCount > 10) {
            $maxCount = 10;
        }

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount($maxCount, $content, sprintf('reponse must contains %d results', $maxCount));
    }

    public function testListActionWithLocale()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title?lang=fr_FR&sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $maxCount = CustomerTitleQuery::create()->count();

        if ($maxCount > 10) {
            $maxCount = 10;
        }

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount($maxCount, $content, sprintf('reponse must contains %d results', $maxCount));

        $firstResult = $content[0];

        $this->assertEquals('fr_FR', $firstResult['LOCALE'], 'the returned locale must be fr_FR');
    }

    public function testGetAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title/1?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content, 'response must contains only 1 result');
    }

    public function testGetActionWithLocale()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title/1?lang=fr_FR&sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content, 'response must contains only 1 result');
        $firstResult = $content[0];
        $this->assertEquals('fr_FR', $firstResult['LOCALE'], 'the returned locale must be fr_FR');
    }

    public function testGetActionWithUnexistingId()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/title/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }

    public function testCreateAction()
    {
        $client = static::createClient();

        $data = [
            "i18n" => [
                [
                    "locale" => "en_US",
                    "short" => "Mr"
                ],
                [
                    "locale" => "fr_FR",
                    "short" => "M.",
                    "long" => "Monsieur"
                ]
            ]
        ];

        $requestContent = json_encode($data);

        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'POST',
            '/api/title?lang=en_US&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $response = $client->getResponse();

        $this->assertEquals(201, $response->getStatusCode(), sprintf(
            'Http status code must be 201. Error: %s',
            $client->getResponse()->getContent()
        ));

        $content = json_decode($response->getContent(), true);

        $this->assertEquals('Mr', $content[0]['SHORT']);
        $this->assertEquals('', $content[0]['LONG']);

        return $content[0]['ID'];
    }

    /**
     * @param $titleId
     * @depends testCreateAction
     */
    public function testUpdateAction($titleId)
    {
        $client = static::createClient();

        $data = [
            "i18n" => [
                [
                    "locale" => "en_US",
                    "long" => "Mister"
                ],
            ],
            "default" => true,
            "title_id" => $titleId,
        ];

        $requestContent = json_encode($data);

        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'PUT',
            '/api/title?lang=en_US&no-cache=yes&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $response = $client->getResponse();

        $this->assertEquals(201, $response->getStatusCode(), sprintf(
            'Http status code must be 201. Error: %s',
            $client->getResponse()->getContent()
        ));

        $content = json_decode($response->getContent(), true);

        $this->assertEquals('Mr', $content[0]['SHORT']);
        $this->assertEquals('Mister', $content[0]['LONG']);
        $this->assertEquals(1, $content[0]['DEFAULT']);

        return $titleId;
    }

    /**
     * @param $titleId
     * @depends testUpdateAction
     */
    public function testUpdateActionWithFormError($titleId)
    {
        $client = static::createClient();

        $data = [
            "i18n" => [
                [
                    "locale" => "en_US",
                    "short" => "This sentence is really too long for a short"
                ],
            ],
            "default" => true,
            "title_id" => $titleId,
        ];

        $requestContent = json_encode($data);

        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'PUT',
            '/api/title?sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $response = $client->getResponse();

        $this->assertEquals(500, $response->getStatusCode(), sprintf(
            'Http status code must be 500. Error: %s',
            $client->getResponse()->getContent()
        ));

        return $titleId;
    }

    /**
     * @param $titleId
     * @depends testUpdateActionWithFormError
     */
    public function testDeleteAction($titleId)
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/api/title/'.$titleId.'?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(204, $client->getResponse()->getStatusCode());
    }

    public function testCreateActionWithNotCompleteForm()
    {
        $client = static::createClient();

        $data = [
            "i18n" => [
                [
                    "short" => "Mr"
                ],
                [
                    "locale" => "fr_FR",
                    "short" => "Mr",
                ],
            ]
        ];

        $requestContent = json_encode($data);

        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'POST',
            '/api/title?sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $response = $client->getResponse();

        $this->assertEquals(500, $response->getStatusCode(), sprintf(
            'Http status code must be 500. Error: %s',
            $client->getResponse()->getContent()
        ));
    }

    public function testUpdateActionWithNotExistingTitleId()
    {
        $client = static::createClient();

        $data = [
            "i18n" => [
                [
                    "locale" => "en_US",
                    "long" => "Mister"
                ],
            ],
            "default" => true,
            "title_id" => PHP_INT_MAX,
        ];

        $requestContent = json_encode($data);

        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'PUT',
            '/api/title?sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $response = $client->getResponse();

        $this->assertEquals(404, $response->getStatusCode(), sprintf(
            'Http status code must be 404. Error: %s',
            $client->getResponse()->getContent()
        ));
    }

    public function testDeleteActionWithNotExistingTitleId()
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/api/title/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }
}
