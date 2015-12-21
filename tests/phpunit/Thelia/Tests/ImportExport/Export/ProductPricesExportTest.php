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
use Thelia\ImportExport\Export\Type\ProductPricesExport;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Lang;

/**
 * Class ProductPricesExportTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductPricesExportTest extends \PHPUnit_Framework_TestCase
{
    public function testQuery()
    {
        new Translator(new Container());
        $export = new ProductPricesExport(new Container());

        $data = $export->buildData(Lang::getDefaultLanguage());

        $keys = ["attributes","currency","ean","id","price","product_id","promo","promo_price","title"];

        $rawData = $data->getData();

        $max = count($rawData);

        /**
         * If there's more that 50 entries,
         * just pick 50, it would be faster and as tested as if we test 1000 entries.
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
            // The substr is a patch for php 5.4 float round
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
        }
    }
}
