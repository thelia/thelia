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

namespace Thelia\Tests\ImportExport\Import;
use Thelia\Core\FileFormat\Formatting\Formatter\JsonFormatter;
use Thelia\ImportExport\Import\Type\ProductStockImport;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductStockImportTest
 * @package Thelia\Tests\ImportExport\Import
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * This class tests the import controller too
 */
class ProductStockImportTest extends ImportTestBase
{
    public function setUp()
    {
        parent::setUp();

        $this->import = new ProductStockImport($this->container);
    }

    public function testUpdateStock()
    {
        $query = ProductSaleElementsQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->limit(3)
            ->find()
        ;

        $jsonData = [];
        $data = [];

        /** @var \Thelia\Model\ProductSaleElements $pse */
        foreach ($query as $pse) {

            $entry = [];

            $entry["ref"]   = $pse->getRef();
            /**
             * Be sure to get a different value.
             */
            while ($pse->getQuantity() === $entry["stock"] = rand(0, 1000));

            $data[$pse->getId()] = $entry["stock"];

            $jsonData[] = $entry;
        }

        $jsonString = json_encode($jsonData);

        $this->assertEquals(
            "Import successfully done",
            $this->controller->processImport(
                $jsonString,
                $this->import,
                new JsonFormatter(),
                null
            )
        );

        $query = ProductSaleElementsQuery::create()->findPks(array_keys($data));

        /** @var \Thelia\Model\ProductSaleElements $entry */
        foreach ($query as $entry) {
            $this->assertEquals(
                $data[$entry->getId()],
                $entry->getQuantity()
            );
        }
    }
} 