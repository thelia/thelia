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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Controller\Admin\ImportController;
use Thelia\Core\FileFormat\Formatting\Formatter\JsonFormatter;
use Thelia\ImportExport\Import\Type\ProductPriceImport;
use Thelia\Model\Currency;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tests\Controller\ControllerTestBase;
use Thelia\Tests\Controller\ImportExportControllerTrait;

/**
 * Class ProductPriceImportTest
 * @package Thelia\Tests\ImportExport\Import
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductPriceImportTest extends ControllerTestBase
{
    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {

    }


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

        $this->import = new ProductPriceImport($this->container);

    }

    public function testImport()
    {
        $currency = Currency::getDefaultCurrency();

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
            while ($pse->getPricesByCurrency($currency)->getPrice() === $entry["price"] = rand(0, 1000));
            while ($pse->getPricesByCurrency($currency)->getPromoPrice() === $entry["promo_price"] = rand(0, 1000));

            $data[$pse->getId()] = $entry;

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
                [
                    "price" => $entry->getPricesByCurrency($currency)->getPrice(),
                    "promo_price" => $entry->getPricesByCurrency($currency)->getPromoPrice(),
                    "ref" => $entry->getRef()
                ]
            );
        }
    }

} 