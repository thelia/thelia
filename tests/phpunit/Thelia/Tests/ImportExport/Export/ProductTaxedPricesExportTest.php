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

namespace Thelia\Tests\ImportExport\Export;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\Type\ProductTaxedPricesExport;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Lang;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductTaxedPricesExportTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 */
class ProductTaxedPricesExportTest extends \PHPUnit_Framework_TestCase
{
    public function testPrices()
    {
        new Translator(new Container());
        $export = new ProductTaxedPricesExport(new Container());

        $data = $export->buildData(Lang::getDefaultLanguage());

        $keys = ["attributes","currency","ean","id","price","product_id","promo","promo_price","tax_id","tax_title","title"];

        $rawData = $data->getData();

        $max = count($rawData);

        /**
         * If there are more than 50 entries, a test on 50 entries will be as efficient
         * and quicker than a test on all the entries
         */
        if ($max > 50) {
            $max = 50;
        }

        for ($i = 0; $i < $max; ++$i) {
            $row = $rawData[$i];

            $rowKeys = array_keys($row);

            $this->assertTrue(sort($rowKeys));
            $this->assertEquals($keys, $rowKeys);

            $pse = ProductSaleElementsQuery::create()
                ->findPk($row["id"])
            ;
            $this->assertNotNull($pse);

            $this->assertEquals($pse->getEanCode(), $row["ean"]);

            $this->assertEquals($pse->getPromo(), $row["promo"]);

            $currency = CurrencyQuery::create()->findOneByCode($row["currency"]);
            $this->assertNotNull($currency);

            $price = $pse->getPricesByCurrency($currency);
            $this->assertEquals(round($price->getPrice(), 3), round($row["price"], 3));
            $this->assertEquals(round($price->getPromoPrice(), 3), round($row["promo_price"], 3));

            $this->assertEquals($pse->getProduct()->getTitle(), $row["title"]);

            $attributeCombinations = $pse->getAttributeCombinations();
            $attributes = [];
            foreach ($attributeCombinations as $attributeCombination) {
                if (!in_array($attributeCombination->getAttributeAv()->getTitle(), $attributes)) {
                    $attributes[] = $attributeCombination->getAttributeAv()->getTitle();
                }
            }
            $rowAttributes = (!empty($row["attributes"])) ? explode(",", $row["attributes"]) : [] ;
            sort($rowAttributes);
            sort($attributes);
            $this->assertEquals($attributes, $rowAttributes);

            $taxId = $pse->getProduct()->getTaxRule()->getId();
            $this->assertEquals($taxId, $row["tax_id"]);

            $taxTitle = $pse->getProduct()->getTaxRule()->getTitle();
            $this->assertEquals($taxTitle, $row["tax_title"]);
        }
    }
}
