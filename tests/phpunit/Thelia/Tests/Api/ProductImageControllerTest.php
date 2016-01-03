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

use Propel\Runtime\Exception\UnexpectedValueException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Model\ProductQuery;
use Thelia\Tests\ApiTestCase;

/**
 * Class ProductImageControllerTest
 * @package Thelia\Tests\Api
 * @author manuel raynaud <manu@raynaud.io>
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductImageControllerTest extends ApiTestCase
{
    public static function setUpBeforeClass()
    {
        $fs = new Filesystem();

        $fs->copy(
            __DIR__ . '/fixtures/base.png',
            __DIR__ . '/fixtures/visuel.png'
        );

        $fs->copy(
            __DIR__ . '/fixtures/base.png',
            __DIR__ . '/fixtures/visuel2.png'
        );

        $fs->copy(
            __DIR__ . '/fixtures/base.png',
            __DIR__ . '/fixtures/visuel3.png'
        );
    }

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

        $product = ProductQuery::create()->joinProductImage()->findOne();

        if (null === $product) {
            $this->markTestSkipped("This test can't be run as there is no product that has an image");
        }

        $productImage = $product->getProductImages()->get(0);

        $client->request(
            'GET',
            '/api/products/'.$product->getId().'/images/'.$productImage->getId().'?sign='.$this->getSignParameter(""),
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

    public function testCreateImageAction()
    {
        $client = static::createClient();

        $servers = $this->getServerParameters();

        $image = new UploadedFile(
            __DIR__ . '/fixtures/visuel.png',
            'visuel.png',
            'image/png'
        );

        $image2 = new UploadedFile(
            __DIR__ . '/fixtures/visuel2.png',
            'visuel2.png',
            'image/png'
        );

        $client->request(
            'POST',
            '/api/products/1/images?&sign='.$this->getSignParameter(''),
            [],
            [
                'image1' => $image,
                'image2' => $image2
            ],
            $servers
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode(), 'Http status code must be 201');

        $content = json_decode($client->getResponse()->getContent(), true);

        $last = array_pop($content);

        return $last['ID'];
    }

    public function testCreateImageActionWithWrongMimeType()
    {
        $client = static::createClient();

        $servers = $this->getServerParameters();

        $image = new UploadedFile(
            __DIR__ . '/fixtures/fail.pdf',
            'fail.png',
            'image/png'
        );


        $client->request(
            'POST',
            '/api/products/1/images?&sign='.$this->getSignParameter(''),
            [],
            [
                'image1' => $image
            ],
            $servers
        );

        $this->assertEquals(500, $client->getResponse()->getStatusCode(), 'Http status code must be 500');
    }

    /**
     * @param $imageId
     * @depends testCreateImageAction
     */
    public function testUpdateImageAction($imageId)
    {
        $client = static::createClient();

        $servers = $this->getServerParameters();

        $image = new UploadedFile(
            __DIR__ . '/fixtures/visuel3.png',
            'visuel3.png',
            'image/png'
        );

        $client->request(
            'PUT',
            '/api/products/1/images/'.$imageId.'?sign='.$this->getSignParameter(''),
            [],
            [
                'image1' => $image
            ],
            $servers
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode(), 'Http status code must be 201');
        $content = json_decode($client->getResponse()->getContent(), true);

        return $imageId;
    }

    /**
     * @param $imageId
     * @depends testCreateImageAction
     */
    public function testUpdateImageDataAction($imageId)
    {
        $client = static::createClient();

        $data = [
            "i18n" => array(
                [
                    "locale" => "en_US",
                    "title" => "My Image",
                    "chapo" => "My Super Image"
                ],
                [
                    "locale" => "fr_FR",
                    "title" => "Mon image",
                    "chapo" => "Ma super image"
                ]
            )
        ];
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $content = json_encode($data);

        $client->request(
            'PUT',
            '/api/products/1/images/'.$imageId.'?lang=en_US&no-cache=yes&sign='.$this->getSignParameter($content),
            [],
            [],
            $servers,
            $content
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode(), 'Http status code must be 201');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals("My Image", $content[0]["TITLE"]);
        $this->assertEquals("My Super Image", $content[0]["CHAPO"]);

        return $imageId;
    }

    /**
     * @param $imageId
     * @depends testCreateImageAction
     */
    public function testUpdateFailsOnPutNothing($imageId)
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            '/api/products/1/images/'.$imageId.'?no-cache=yes&sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(500, $client->getResponse()->getStatusCode(), 'Http status code must be 500');

        return $imageId;
    }

    /**
     * @param $imageId
     * @depends testUpdateImageAction
     */
    public function testDeleteImageAction($imageId)
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/api/products/1/images/'.$imageId.'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(204, $client->getResponse()->getStatusCode(), 'Http status code must be 204');
    }

    public function testUpdateImageFailWithBadId()
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            '/api/products/1/images/'.PHP_INT_MAX.'?no-cache=yes&sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }
}
