<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\ImportExport\Export\Type;

use PDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;

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
        'product_sale_elements_id' => 'id',
        'product_sale_elements_product_id' => 'product_id',
        'product_i18n_title' => 'title',
        'attribute_av_i18n_title' => 'attributes',
        'product_sale_elements_ean_code' => 'ean',
        'product_price_price' => 'price',
        'product_price_promo_price' => 'promo_price',
        'currency_code' => 'currency',
        'product_sale_elements_promo' => 'promo',
        'tax_rule_i18n_id' => 'tax_id',
        'tax_rule_i18n_title' => 'tax_title'
    ];

    public function getData()
    {
        $locale = $this->language->getLocale();

        $con = Propel::getConnection();
        $query = 'SELECT
                        product_sale_elements.id as "product_sale_elements_id",
                        product_sale_elements.product_id as "product_sale_elements_product_id",
                        product_i18n.title as "product_i18n_title",
                        attribute_av_i18n.title as "attribute_av_i18n_title",
                        product_sale_elements.ean_code as "product_sale_elements_ean_code",
                        product_price.price as "product_price_price",
                        product_price.promo_price as "product_price_promo_price",
                        currency.code as "currency_code",
                        product_sale_elements.promo as "product_sale_elements_promo",
                        tax_rule_i18n.id as "tax_rule_i18n_id",
                        tax_rule_i18n.title as "tax_rule_i18n_title"
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

        return $this->getDataJsonCache($stmt, 'product_taxed_prices');
    }
}
