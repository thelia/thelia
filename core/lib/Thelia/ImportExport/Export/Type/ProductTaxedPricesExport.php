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

namespace Thelia\ImportExport\Export\Type;

use PDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;
use Thelia\Model\Map\AttributeAvI18nTableMap;
use Thelia\Model\Map\AttributeAvTableMap;
use Thelia\Model\Map\CurrencyTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\TaxRuleI18nTableMap;
use Thelia\Model\Map\TaxRuleTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductTaxedPricesExport
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class ProductTaxedPricesExport extends JsonFileAbstractExport
{
    const FILE_NAME = 'product_taxed_price';

    protected $orderAndAliases = [
        ProductSaleElementsTableMap::COL_ID => 'id',
        ProductSaleElementsTableMap::COL_PRODUCT_ID => 'product_id',
        ProductI18nTableMap::COL_TITLE => 'title',
        AttributeAvI18nTableMap::COL_TITLE => 'attributes',
        ProductSaleElementsTableMap::COL_EAN_CODE => 'ean',
        ProductPriceTableMap::COL_PRICE => 'price',
        ProductPriceTableMap::COL_PROMO_PRICE => 'promo_price',
        CurrencyTableMap::COL_CODE => 'currency',
        ProductSaleElementsTableMap::COL_PROMO => 'promo',
        TaxRuleI18nTableMap::COL_ID => 'tax_id',
        TaxRuleI18nTableMap::COL_TITLE => 'tax_title'
    ];

    public function getData()
    {
        $locale = $this->language->getLocale();

        $con = Propel::getConnection();
        $query = 'SELECT 
                        product_sale_elements.id as "product_sale_elements.id",
                        product_sale_elements.product_id as "product_sale_elements.product_id",
                        product_i18n.title as "product_i18n.title",
                        attribute_av_i18n.title as "attribute_av_i18n.title",
                        product_sale_elements.ean_code as "product_sale_elements.ean_code",
                        product_price.price as "product_price.price",
                        product_price.promo_price as "product_price.promo_price",
                        currency.code as "currency.code",
                        product_sale_elements.promo as "product_sale_elements.promo",
                        tax_rule_i18n.id as "tax_rule_i18n.id",
                        tax_rule_i18n.title as "tax_rule_i18n.title"
                    FROM product_sale_elements
                    LEFT JOIN product ON product.id = product_sale_elements.product_id
                    LEFT JOIN product_i18n ON product_i18n.id = product.id AND product_i18n.locale = :locale
                    LEFT JOIN attribute_combination ON attribute_combination.product_sale_elements_id = product_sale_elements.id
                    LEFT JOIN attribute_av_i18n ON attribute_av_i18n.id = attribute_combination.attribute_av_id AND attribute_av_i18n.locale = :locale
                    LEFT JOIN product_price ON product_price.product_sale_elements_id = product_sale_elements.id
                    LEFT JOIN currency ON currency.id = product_price.currency_id
                    LEFT JOIN tax_rule_i18n ON tax_rule_i18n.id = product.tax_rule_id AND tax_rule_i18n.locale = :locale
                    ORDER BY product.id'
        ;

        $stmt = $con->prepare($query);
        $stmt->bindValue('locale', $locale);
        $stmt->execute();

        $filename = THELIA_CACHE_DIR . '/export/' . 'product_seo.json';

        if (file_exists($filename)) {
            unlink($filename);
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            file_put_contents($filename, json_encode($row) . "\r\n", FILE_APPEND);
        }

        return $filename;
    }
}
