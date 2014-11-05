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

namespace Thelia\Tests\FileFormat\Formatting;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;

/**
 * Class FormatterDataTest
 * @package Thelia\Tests\FileFormat\Formatting
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FormatterDataTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        new Translator(new Container());
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

        $this->assertEquals($formattedResult, $formattedData);
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

        $this->assertEquals($formattedResult, $formattedData);
    }

    public function testFormatComplexQuery()
    {
        $formatterData = new FormatterData();

        $query = ProductSaleElementsQuery::create()
            ->useProductQuery()
                ->addAsColumn("\"".ProductTableMap::ID."\"", ProductTableMap::ID)
            ->endUse()
            ->select(
                [
                    ProductSaleElementsTableMap::ID,
                    ProductSaleElementsTableMap::QUANTITY,
                ]
            )
            ->limit(1)
        ;

        $formattedData = $formatterData
            ->loadModelCriteria($query)
            ->getData()
        ;
        /** @var array $data */
        $data = $query->findOne();

        $expectedData = [
            [
                "product.id" => $data["product.ID"],
                "product_sale_elements.id" => $data["product_sale_elements.ID"],
                "product_sale_elements.quantity" => $data["product_sale_elements.QUANTITY"],
            ]
        ];

        $this->assertEquals($expectedData, $formattedData);
    }

    public function testFormatComplexQueryWithAliases()
    {
        $aliases = [
            "product.id" => "pid",
            "product_sale_elements.id" => "pseid",
            "product_sale_elements.quantity" => "stock"
        ];

        $formatterData = new FormatterData($aliases);

        $query = ProductSaleElementsQuery::create()
            ->useProductQuery()
            ->addAsColumn("\"".ProductTableMap::ID."\"", ProductTableMap::ID)
            ->endUse()
            ->select(
                [
                    ProductSaleElementsTableMap::ID,
                    ProductSaleElementsTableMap::QUANTITY,
                ]
            )
            ->limit(1)
        ;

        $formattedData = $formatterData
            ->loadModelCriteria($query)
            ->getData()
        ;
        /** @var array $data */
        $data = $query->findOne();

        $expectedData = [
            [
                "pid" => $data["product.ID"],
                "pseid" => $data["product_sale_elements.ID"],
                "stock" => $data["product_sale_elements.QUANTITY"],
            ]
        ];

        $this->assertEquals($expectedData, $formattedData);
    }

    public function testSetRawDataDepth1()
    {
        $formatterData = new FormatterData();

        $data = [
            "foo" => "bar",
            "baz" => "foo",
        ];

        $formattedData = $formatterData
            ->setData($data)
            ->getData()
        ;

        $this->assertEquals($data, $formattedData);
    }

    public function testSetRawDataDepth1WithAliases()
    {
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

        $this->assertEquals($expectedData, $formattedData);
    }

    public function testSetRawDataDepth2()
    {
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

        $this->assertEquals($data, $formattedData);
    }

    public function testSetRawDataDepth2WithAliases()
    {
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

        $this->assertEquals($expectedData, $formattedData);
    }

    public function testSetRawDataMultipleDepth()
    {
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

        $this->assertEquals($data, $formattedData);
    }

    public function testSetRawDataMultipleDepthWithAliases()
    {
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

        $this->assertEquals($expectedData, $formattedData);
    }

    public function testSetRawDataMultipleDepthWithReverseAliases()
    {
        $aliases = [
            "orange" => "foo",
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

        $formattedData = $formatterData
            ->setData($data)
            ->getDataReverseAliases()
        ;

        $this->assertEquals($data, $formattedData);
    }

    /**
     * That's why an alias MUST not be the same as a present value
     */
    public function testSetRawDataMultipleDepthWithReverseAliasesFail()
    {
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

        $formattedData = $formatterData
            ->setData($data)
            ->getDataReverseAliases()
        ;

        $this->assertNotEquals($data, $formattedData);
    }

    public function testAddRow()
    {
        $data = new FormatterData();

        $row = [
            "title" => "A super book",
            "author" => "Manu",
        ];

        $data->addRow($row);

        $this->assertEquals([$row], $data->getData());
        $this->assertEquals($row, $data->getRow());
    }

    public function testPopRow()
    {
        $data = new FormatterData();

        $row = [
            "title" => "A super book",
            "author" => "Manu",
        ];

        $data->addRow($row);

        $this->assertEquals($row, $data->popRow());
        $this->assertFalse($data->getRow());
    }
}
