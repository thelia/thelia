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

use Thelia\Model\CategoryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Tests\ApiTestCase;

/**
 * Class ProductControllerTest
 * @package Thelia\Tests\Api
 * @author Manuel Raynaud <manu@raynaud.io>
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
            '/api/products?sign='.$this->getSignParameter(""),
            [],
            [],
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
            '/api/products?limit=2&sign='.$this->getSignParameter(""),
            [],
            [],
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
            '/api/products/1?sign='.$this->getSignParameter(""),
            [],
            [],
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
            '/api/products/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }

    public function testCreateAction()
    {
        $client  = static::createClient();

        $category = CategoryQuery::create()->addAscendingOrderByColumn('RAND()')->findOne();
        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(1);
        $taxRule = TaxRuleQuery::create()->findOneByIsDefault(1);
        $product = [
            'ref' => uniqid('testCreateProduct'),
            'locale' => 'en_US',
            'title' => 'product create from api',
            'description' => 'product description from api',
            'default_category' => $category->getId(),
            'visible' => 1,
            'price' => '10',
            'currency' => $defaultCurrency->getId(),
            'tax_rule' => $taxRule->getId(),
            'weight' => 10,
            'brand_id' => 0
        ];

        $requestContent = json_encode($product);
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'POST',
            '/api/products?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode(), 'Http status code must be 201');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('en_US', $content[0]['LOCALE']);

        return $content['0']['ID'];
    }

    public function testCreateWithOptionalParametersAction()
    {
        $client  = static::createClient();

        $category = CategoryQuery::create()->addAscendingOrderByColumn('RAND()')->findOne();
        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(1);
        $taxRule = TaxRuleQuery::create()->findOneByIsDefault(1);
        $templateId = $category->getDefaultTemplateId();

        if (null === $templateId) {
            $templateId = TemplateQuery::create()->addAscendingOrderByColumn('RAND()')->findOne()->getId();
        }

        $product = [
            'ref' => uniqid('testCreateProduct'),
            'locale' => 'en_US',
            'title' => 'product create from api',
            'description' => 'product description from api',
            'default_category' => $category->getId(),
            'visible' => 1,
            'price' => '10',
            'currency' => $defaultCurrency->getId(),
            'tax_rule' => $taxRule->getId(),
            'weight' => 10,
            'brand_id' => 0,
            'quantity' => 10,
            'template_id' => $templateId
        ];

        $requestContent = json_encode($product);
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'POST',
            '/api/products?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode(), 'Http status code must be 201');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('en_US', $content[0]['LOCALE']);

        $this->assertEquals(10, $content[0]['QUANTITY']);
        $this->assertEquals($templateId, $content[0]['TEMPLATE']);
    }

    /**
     * @param $productId
     * @depends testCreateAction
     */
    public function testUpdateAction($productId)
    {
        $client  = static::createClient();

        $product = ProductQuery::create()->findPk($productId);

        $productData = [
            'ref' => $product->getRef(),
            'locale' => 'en_US',
            'title' => 'product updated from api',
            'default_category' => $product->getDefaultCategoryId(),
            'visible' => 1,
            'description' => 'product description updated from api',
            'chapo' => 'product chapo updated from api',
            'postscriptum' => 'product postscriptum',
            'brand_id' => 0
        ];

        $requestContent = json_encode($productData);
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'PUT',
            '/api/products/'.$productId.'?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(204, $client->getResponse()->getStatusCode(), 'Http status code must be 204');

        return $productId;
    }

    /**
     * @param $productId
     * @depends testUpdateAction
     */
    public function testDeleteAction($productId)
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/api/products/'.$productId.'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(204, $client->getResponse()->getStatusCode(), 'Http status code must be 204');
    }

    public function testDeleteActionWithNonExistingProduct()
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/api/products/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }
}
