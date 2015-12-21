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
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\AttributeAvTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Tests\ApiTestCase;

/**
 * Class ProductSaleElementsControllerTest
 * @package Thelia\Tests\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductSaleElementsControllerTest extends ApiTestCase
{
    /**
     * @covers \Thelia\Controller\Api\ProductController::listAction
     */
    public function testListAction()
    {
        $client = static::createClient();

        $product = $this->getProduct();

        $client->request(
            'GET',
            '/api/pse/product/'.$product->getId().'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $productPse = $product->getProductSaleElementss()->count();

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(
            $productPse > 10 ? 10 : $productPse,
            $content,
            'without parameters, the api must return 10 results, or the number of PSE of the product'
        );
    }

    /**
     * @covers \Thelia\Controller\Api\ProductController::listAction
     */
    public function testListActionWithLimit()
    {
        $client = static::createClient();
        $product = $this->getProduct();

        $client->request(
            'GET',
            '/api/pse/product/'.$product->getId().'?limit=1&sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content, 'with the limit parameter at 1, the api must return 1 result');
    }

    /**
     * @covers \Thelia\Controller\Api\ProductController::getAction
     */
    public function testGetAction()
    {
        $client = static::createClient();

        $pse = ProductSaleElementsQuery::create()->findOne();

        if (null === $pse) {
            $this->markTestSkipped(
                sprintf("You can't run this test without any product sale elements")
            );
        }

        $client->request(
            'GET',
            '/api/pse/'.$pse->getId().'?sign='.$this->getSignParameter(""),
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
            '/api/pse/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }


    public function testCreateAction()
    {
        $client  = static::createClient();

        $product = $this->getProduct();
        $currency = $this->getCurrency();
        $taxRule = $this->getTaxRule();

        $attributeAvs = AttributeAvQuery::create()
            ->limit(2)
            ->select(AttributeAvTableMap::ID)
            ->find()
            ->toArray()
        ;

        $data = [
            "pse" => [
                [
                    "product_id" => $product->getId(),
                    "tax_rule_id" => $taxRule->getId(),
                    "currency_id" => $currency->getId(),
                    "price" => "3.99",
                    "reference" => "foo",
                    "attribute_av" => $attributeAvs,
                    "onsale" => true,
                    "isnew" => true,
                ]
            ]
        ];

        $requestContent = json_encode($data);

        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'POST',
            '/api/pse?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(
            201,
            $client->getResponse()->getStatusCode(),
            sprintf(
                'Http status code must be 201. Error: %s',
                $client->getResponse()->getContent()
            )
        );

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('3.99', $content[0]['PRICE']);

        return $content['0']['ID'];
    }

    /**
     * @param $pseId
     * @depends testCreateAction
     */
    public function testUpdateAction($pseId)
    {
        $client  = static::createClient();

        $pseData = [
            "pse" => [
                [
                    "id" => $pseId,
                    "price" => "3.33",
                    "sale_price" => "2.11",
                ]
            ]
        ];

        $requestContent = json_encode($pseData);
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'PUT',
            '/api/pse/'.$pseId.'?&sign='.$this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(201, $client->getResponse()->getStatusCode(), 'Http status code must be 204');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('3.33', $data["0"]["PRICE"]);
        $this->assertEquals('2.11', $data["0"]["PROMO_PRICE"]);
        $this->assertEquals('foo', $data["0"]["REF"]);

        return $pseId;
    }

    /**
     * @param $pseId
     * @depends testUpdateAction
     */
    public function testDeleteAction($pseId)
    {
        $client = static::createClient();

        $pse = ProductSaleElementsQuery::create()->findPk($pseId);

        if (null === $pse) {
            $this->markTestSkipped(
                sprintf("You can't run this test without any product sale elements")
            );
        }

        $client->request(
            'DELETE',
            '/api/pse/'.$pse->getId().'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(204, $client->getResponse()->getStatusCode(), 'Http status code must be 204');
    }

    public function testCreateMultiplePSEInOneShot()
    {
        $client  = static::createClient();

        $product = $this->getProduct();
        $currency = $this->getCurrency();
        $taxRule = $this->getTaxRule();

        $attributeAvs = AttributeAvQuery::create()
            ->limit(2)
            ->select(AttributeAvTableMap::ID)
            ->find()
            ->toArray();

        $data = [
            "pse" => [
                [
                    "product_id" => $product->getId(),
                    "tax_rule_id" => $taxRule->getId(),
                    "currency_id" => $currency->getId(),
                    "price" => "3.12",
                    "reference" => "foo",
                    "quantity" => 1,
                    "attribute_av" => $attributeAvs,
                    "onsale" => true,
                    "isnew" => true,
                ],
                [
                    "product_id" => $product->getId(),
                    "tax_rule_id" => $taxRule->getId(),
                    "currency_id" => $currency->getId(),
                    "price" => "3.33",
                    "reference" => "bar",
                    "quantity" => 10,
                    "attribute_av" => [$attributeAvs[0]],
                    "onsale" => true,
                    "isnew" => true,
                ],
                [
                    "product_id" => $product->getId(),
                    "tax_rule_id" => $taxRule->getId(),
                    "currency_id" => $currency->getId(),
                    "price" => "12.09",
                    "reference" => "baz",
                    "quantity" => 100,
                    "attribute_av" => [$attributeAvs[1]],
                    "onsale" => true,
                    "isnew" => true,
                ]
            ]
        ];

        $requestContent = json_encode($data);

        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'POST',
            '/api/pse?order=quantity&sign=' . $this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(
            201,
            $client->getResponse()->getStatusCode(),
            sprintf(
                'Http status code must be 201. Error: %s',
                $client->getResponse()->getContent()
            )
        );

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(3, $content);
        $this->assertEquals('3.12', $content[0]['PRICE']);
        $this->assertEquals('3.33', $content[1]['PRICE']);
        $this->assertEquals('12.09', $content[2]['PRICE']);

        $ids = array();

        foreach ($content as $entry) {
            $ids[] = $entry["ID"];
        }

        return $ids;
    }

    /**
     * @param $ids
     * @depends testCreateMultiplePSEInOneShot
     */
    public function testUpdateMultiplePSEInOneShot($ids)
    {
        $client  = static::createClient();

        $data = [
            "pse" => [
                [
                    "id" => $ids[0],
                    "price" => "3.50",
                ],
                [
                    "id" => $ids[1],
                    "price" => "2.54",
                ],
                [
                    "id" => $ids[2],
                    "price" => "9.60",
                ]
            ]
        ];

        $requestContent = json_encode($data);

        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';

        $client->request(
            'PUT',
            '/api/pse?order=quantity&sign=' . $this->getSignParameter($requestContent),
            [],
            [],
            $servers,
            $requestContent
        );

        $this->assertEquals(
            201,
            $client->getResponse()->getStatusCode(),
            sprintf(
                'Http status code must be 201. Error: %s',
                $client->getResponse()->getContent()
            )
        );

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(3, $content);
        $this->assertEquals('3.50', $content[0]['PRICE']);
        $this->assertEquals('2.54', $content[1]['PRICE']);
        $this->assertEquals('9.60', $content[2]['PRICE']);
    }

    public function testDeleteActionWithNonExistingProduct()
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/api/pse/'.PHP_INT_MAX.'?sign='.$this->getSignParameter(""),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), 'Http status code must be 404');
    }

    /**
     * Not tests
     */

    protected function getProduct()
    {
        $product = ProductQuery::create()->findOne();

        if (null === $product) {
            $this->markTestSkipped("You must have at least one product to run this test");
        }

        return $product;
    }

    protected function getTaxRule()
    {
        $product = TaxRuleQuery::create()->findOne();

        if (null === $product) {
            $this->markTestSkipped("You must have at least one tax rule to run this test");
        }

        return $product;
    }

    protected function getCurrency()
    {
        $product = CurrencyQuery::create()->findOneByCode("EUR");

        if (null === $product) {
            $this->markTestSkipped("You must have at least one currency to run this test");
        }

        return $product;
    }
}
