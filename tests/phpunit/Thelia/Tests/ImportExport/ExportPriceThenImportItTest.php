<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace Thelia\Tests\ImportExport;

use Propel\Runtime\Propel;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\Type\ProductPricesExport;
use Symfony\Component\DependencyInjection\Container;
use Thelia\ImportExport\Import\Type\ProductPricesImport;
use Thelia\Model\Lang;

/**
 * Class ExportPriceThenImportItTest
 * @package Thelia\Tests\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportPriceThenImportItTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ProductPricesExport
     */
    protected $exportHandler;

    /**
     * @var ProductPricesImport
     */
    protected $importHandler;

    /**
     * @var \Propel\Runtime\Connection\ConnectionInterface
     */
    protected $con;

    protected function setUp()
    {
        // Initialize translator
        new Translator(new Container());
        $this->exportHandler = new ProductPricesExport(new Container());
        $this->importHandler = new ProductPricesImport(new Container());

        $this->con = Propel::getConnection();
        $this->con->beginTransaction();
    }

    protected function tearDown()
    {
        $this->con->rollBack();
    }

    public function testImportWorks()
    {
        // Export data
        $data = $this->exportHandler->buildData(Lang::getDefaultLanguage());
        $compareData = array();
        $currentData = $data->getData();

        // Replace the prices
        foreach ($currentData as $key => &$entry) {
            // let  6/10 prices be changed.
            if (rand(1, 100) >= 60) {
                $compareData[$key] = $rand = rand(1, 1000);
                $entry["price"] = $rand;
            } else {
                $compareData[$key] = $entry["price"];
            }
        }

        // Import new prices
        $this->importHandler->retrieveFromFormatterData($data->setData($currentData));

        // Export once again
        $newData = $this->exportHandler->buildData(Lang::getDefaultLanguage());
        $newDataEntries = $newData->getData();
        // Check them
        foreach ($compareData as $key => $price) {
            $this->assertEquals($price, $newDataEntries[$key]["price"]);
        }
    }
}
