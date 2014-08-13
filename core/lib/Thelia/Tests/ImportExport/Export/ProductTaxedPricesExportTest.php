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
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductTaxedPricesExportTest extends \PHPUnit_Framework_TestCase
{
    public function testPrices()
    {
        $container =  new Container();
        new Translator($container);

        $handler = new ProductTaxedPricesExport($container);

        $lang = Lang::getDefaultLanguage();
        $data = $handler->buildData($lang)->getData();

        foreach ($data as $line) {
            $product = ProductSaleElementsQuery::create()->findOneByRef($line["ref"]);
            $currency = CurrencyQuery::create()->findOneByCode($line["currency"]);

            $this->assertNotNull($product);

            $prices = $product->getPricesByCurrency($currency);

            $this->assertEquals($prices->getPrice(), $line["price"]);
            $this->assertEquals($prices->getPromoPrice(), $line["promo_price"]);
        }
    }
} 