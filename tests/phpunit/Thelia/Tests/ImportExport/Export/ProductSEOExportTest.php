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
use Thelia\ImportExport\Export\Type\ProductSEOExport;
use Thelia\Model\Lang;
use Thelia\Model\ProductQuery;

/**
 * Class ProductSEOExportTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductSEOExportTest extends \PHPUnit_Framework_TestCase
{
    public function testQuery()
    {
        new Translator(new Container());
        $export = new ProductSEOExport(new Container());

        $data = $export->buildData(Lang::getDefaultLanguage());

        $keys=["ref","visible","product_title","url","page_title","meta_description","meta_keywords",];
        sort($keys);
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

            $product = ProductQuery::create()->findOneByRef($row["ref"]);
            $this->assertNotNull($product);

            $this->assertEquals($product->getVisible(), $row["visible"]);
            $this->assertEquals($product->getTitle(), $row["product_title"]);

            $this->assertEquals($product->getMetaTitle(), $row["page_title"]);
            $this->assertEquals($product->getMetaDescription(), $row["meta_description"]);
            $this->assertEquals($product->getMetaKeywords(), $row["meta_keywords"]);
            $this->assertEquals($product->getRewrittenUrl("en_US"), $row["url"]);
        }
    }
}
