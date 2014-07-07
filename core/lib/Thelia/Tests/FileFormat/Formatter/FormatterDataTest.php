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

namespace Thelia\Tests\FileFormat\Formatter;
use Propel\Generator\Builder\Om\QueryBuilder;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\DataFetcher\ArrayDataFetcher;
use Propel\Runtime\Formatter\ArrayFormatter;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\FileFormat\Formatter\FormatterData;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;

/**
 * Class FormatterDataTest
 * @package Thelia\Tests\FileFormat\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FormatterDataTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        new Translator(new Container());

        /*
            ->filterById([3,4,5], Criteria::IN);

        $this->data->loadModelCriteria($query);

        $query = ProductSaleElementsQuery::create()
            ->joinProduct()
            ->select(["ProductSaleElements.id", "Product.id"])
            ->filterById([3,4,5], Criteria::IN)
        ;

        $this->data->loadModelCriteria($query);

        $query = ProductSaleElementsQuery::create()
            ->joinProduct()
            ->select(["ProductSaleElements.id"])
            ->filterById([3,4,5], Criteria::IN)
        ;

        $this->data->loadModelCriteria($query);

        $query = ProductQuery::create()
            ->joinProductSaleElements()
            ->filterById([3,4,5], Criteria::IN);

        $this->data->loadModelCriteria($query);*/
    }

    public function testFormatSimpleQuery()
    {
        $formatterData = new FormatterData();

        $query = ConfigQuery::create()
            ->limit(1)
        ;

        $formattedData = $formatterData
            ->loadModelCriteria($query)
            ->getData()
        ;

        /** @var \Thelia\Model\Config $result */
        $result = $query->findOne();

        $formattedResult = [
            [
                "config.id" => $result->getId(),
                "config.name" => $result->getName(),
                "config.value" => $result->getValue(),
                "config.created_at" => $result->getCreatedAt(),
                "config.updated_at" => $result->getUpdatedAt(),
                "config.hidden" => $result->getHidden(),
                "config.secured" => $result->getHidden(),
            ],
        ];

        $this->assertEquals($formattedResult,$formattedData);
    }

    public function testFormatSimpleQueryWithAliases()
    {
        /**
         * Aliases must not be case sensitive
         */
        $aliases = [
            "coNfiG.iD" => "id",
            "conFig.NaMe" => "name",
            "CoNfIg.Value" => "value",
            "config.hidden" => "hidden",
            "ConFig.Secured" => "secured",
        ];

        $formatterData = new FormatterData($aliases);

        $query = ConfigQuery::create()
            ->limit(1)
        ;

        $formattedData = $formatterData
            ->loadModelCriteria($query)
            ->getData()
        ;

        /** @var \Thelia\Model\Config $result */
        $result = $query->findOne();

        $formattedResult = [
            [
                "id" => $result->getId(),
                "name" => $result->getName(),
                "value" => $result->getValue(),
                "config.created_at" => $result->getCreatedAt(),
                "config.updated_at" => $result->getUpdatedAt(),
                "hidden" => $result->getHidden(),
                "secured" => $result->getHidden(),
            ],
        ];

        $this->assertEquals($formattedResult,$formattedData);
    }

    public function testFormatSimpleMultipleTableQuery()
    {
        $formatterData = new FormatterData();

        
    }

    public function testFormatSimpleMultipleTableQueryWithAliases()
    {
        /**
         * Aliases must not be case sensitive
         */
        $aliases = [
            "coNfiG.iD" => "id",
            "conFig.NaMe" => "name",
            "CoNfIg.Value" => "value",
            "config.hidden" => "hidden",
            "ConFig.Secured" => "secured",
        ];

        $formatterData = new FormatterData($aliases);

        $query = ConfigQuery::create()
            ->limit(1)
        ;

        $formattedData = $formatterData
            ->loadModelCriteria($query)
            ->getData()
        ;

        /** @var \Thelia\Model\Config $result */
        $result = $query->findOne();

        $formattedResult = [
            [
                "id" => $result->getId(),
                "name" => $result->getName(),
                "value" => $result->getValue(),
                "config.created_at" => $result->getCreatedAt(),
                "config.updated_at" => $result->getUpdatedAt(),
                "hidden" => $result->getHidden(),
                "secured" => $result->getHidden(),
            ],
        ];

        $this->assertEquals($formattedResult,$formattedData);
    }

    public function testSetRawDataDepth1() {
        $formatterData = new FormatterData();

        $data = [
            "foo" => "bar",
            "baz" => "foo",
        ];

        $formattedData = $formatterData
            ->setData($data)
            ->getData()
        ;

        $this->assertEquals($data,$formattedData);
    }

    public function testSetRawDataDepth1WithAliases() {
        $aliases = [
            "FoO" => "orange",
            "Baz" => "banana",
        ];

        $formatterData = new FormatterData($aliases);

        $data = [
            "fOo" => "bar",
            "bAZ" => "foo",
        ];

        $expectedData = [
            "orange" => "bar",
            "banana" => "foo",
        ];

        $formattedData = $formatterData
            ->setData($data)
            ->getData()
        ;

        $this->assertEquals($expectedData,$formattedData);
    }

    public function testSetRawDataDepth2() {
        $formatterData = new FormatterData();

        $data = [
            [
                "orange" => "banana",
                "apple" => "pear",
            ],
            [
                "strawberry" => "raspberry",
                "blackberry" => "cranberry",
            ]
        ];

        $formattedData = $formatterData
            ->setData($data)
            ->getData()
        ;

        $this->assertEquals($data,$formattedData);
    }

    public function testSetRawDataDepth2WithAliases() {
        $aliases = [
            "orange" => "cherry",
            "blackberry" => "banana",
        ];

        $formatterData = new FormatterData($aliases);

        $data = [
            [
                "orange" => "banana",
                "apple" => "pear",
            ],
            [
                "strawberry" => "raspberry",
                "blackberry" => "cranberry",
            ]
        ];

        $expectedData = [
            [
                "cherry" => "banana",
                "apple" => "pear",
            ],
            [
                "strawberry" => "raspberry",
                "banana" => "cranberry",
            ]
        ];

        $formattedData = $formatterData
            ->setData($data)
            ->getData()
        ;

        $this->assertEquals($expectedData,$formattedData);
    }

    public function testSetRawDataMultipleDepth() {
        $formatterData = new FormatterData();

        $data = [
            [
                "orange" => "banana",
                "apple" => "pear",
            ],
            [
                "strawberry" => "raspberry",
                "blackberry" => "cranberry",
            ]
        ];

        $formattedData = $formatterData
            ->setData($data)
            ->getData()
        ;

        $this->assertEquals($data,$formattedData);
    }

    public function testSetRawDataMultipleDepthWithAliases() {
        $aliases = [
            "orange" => "cherry",
            "blackberry" => "banana",
        ];

        $formatterData = new FormatterData($aliases);

        $data = [
            "orange" => "banana",
            "apple" => "pear",
            [
                "orange" => "tomato",
                "pepper" => "pear",
            ],
            [
                [
                    "strawberry" => "raspberry",
                    "blackberry" => "cranberry",
                ],
                [
                    "cherry" => "lemon",
                    "mango" => "cranberry",
                ]
            ],
        ];

        $expectedData = [
            "cherry" => "banana",
            "apple" => "pear",
            [
                "cherry" => "tomato",
                "pepper" => "pear",
            ],
            [
                [
                    "strawberry" => "raspberry",
                    "banana" => "cranberry",
                ],
                [
                    "cherry" => "lemon",
                    "mango" => "cranberry",
                ]
            ],
        ];

        $formattedData = $formatterData
            ->setData($data)
            ->getData()
        ;

        $this->assertEquals($expectedData,$formattedData);
    }

}
