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
use Thelia\Model\Brand;
use Thelia\Model\BrandQuery;
use Thelia\Tests\ApiTestCase;

/**
 * Class BrandControllerTest
 * @package Thelia\Tests\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class BrandControllerTest extends ApiTestCase
{
    protected $created = false;

    protected function setUp()
    {
        if (!$this->created) {
            if (BrandQuery::create()->count() === 0) {
                $brand = new Brand();

                $brand
                    ->getTranslation()
                    ->setTitle("Foo")
                    ->setChapo("Bar")
                    ->setDescription("Baz")
                ;

                $brand
                    ->getTranslation("fr_FR")
                    ->setTitle("orange")
                    ->setChapo("banana")
                    ->setDescription("apple")
                ;

                $brand->save();
            }

            $this->created = true;
        }
    }

    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/brands?sign='.$this->getSignParameter(''),
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
            '/api/brands?lang=fr_FR&sign='.$this->getSignParameter(''),
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

        $brand = $this->getBrand();

        $client->request(
            'GET',
            '/api/brands/'.$brand->getId().'?sign='.$this->getSignParameter(''),
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

        $brand = $this->getBrand("fr_FR");

        $client->request(
            'GET',
            '/api/brands/'.$brand->getId().'?lang=fr_FR&sign='.$this->getSignParameter(''),
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

    protected function getBrand($locale = 'en_US')
    {
        $brand = BrandQuery::create()
            ->joinBrandI18n("brand_i18n_join", Criteria::INNER_JOIN)
            ->addJoinCondition('brand_i18n_join', "locale = ?", $locale, null, \PDO::PARAM_STR)
            ->findOne()
        ;

        if (null === $brand) {
            $this->markTestSkipped(
                sprintf(
                    "You must have at least one brand with an i18n that has the '%s' locale",
                    $locale
                )
            );
        }

        return $brand;
    }
}
