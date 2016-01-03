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

use Thelia\Controller\Admin\ImportController;
use Thelia\Core\FileFormat\Formatting\Formatter\JsonFormatter;
use Thelia\ImportExport\Import\Type\ProductStockImport;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tests\Controller\ControllerTestBase;
use Thelia\Tests\Controller\ImportExportControllerTrait;

/**
 * Class ProductStockImportTest
 * @package Thelia\Tests\ImportExport\Import
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductStockImportTest extends ControllerTestBase
{
    use ImportExportControllerTrait;

    /**
     * @return \Thelia\Controller\BaseController The controller you want to test
     */
    protected function getController()
    {
        return new ImportController();
    }

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

            $entry["id"]   = $pse->getId();
            /**
             * Be sure to get a different value.
             */
            while ($pse->getQuantity() === $entry["stock"] = rand(0, 1000));

            $data[$pse->getId()] = $entry["stock"];

            $jsonData[] = $entry;
        }

        $jsonString = json_encode($jsonData);

        $this->assertEquals(
            "Import successfully done, 3 row(s) have been changed",
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
