<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\DataTransfer\Export\Type;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Propel;
use Thelia\Domain\DataTransfer\Export\JsonFileAbstractExport;

/**
 * Class ProductPricesExport.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 *
 * @contributor Thomas Arnaud <tarnaud@openstudio.fr>
 *
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class ProductPricesExport extends JsonFileAbstractExport
{
    public const FILE_NAME = 'product_price';

    protected array $orderAndAliases = [
        'product_sale_elements_id' => 'id',
        'product_i18n_id' => 'product_id',
        'product_i18n_title' => 'product_title',
        'attribute_av_i18n_title' => 'attributes',
        'product_sale_elements_ean_code' => 'ean',
        'product_price_price' => 'price',
        'product_price_promo_price' => 'promo_price',
        'currency_code' => 'currency',
        'product_sale_elements_promo' => 'promo',
    ];

    protected function getData(): array|string|ModelCriteria
    {
        $locale = $this->language->getLocale();

        $con = Propel::getConnection();
        $query = 'SELECT
                        product_sale_elements.id as "product_sale_elements_id",
                        product_i18n.id as "product_i18n_id",
                        product_i18n.title as "product_i18n_title",
                        attribute_av_i18n.title as "attribute_av_i18n_title",
                        product_sale_elements.ean_code as "product_sale_elements_ean_code",
                        ROUND(product_price.price, 2) as "product_price_price",
                        ROUND(product_price.promo_price, 2) as "product_price_promo_price",
                        currency.code as "currency_code",
                        product_sale_elements.promo as "product_sale_elements_promo"
                    FROM product_sale_elements
                    LEFT JOIN product_i18n ON product_i18n.id = product_sale_elements.product_id
                    LEFT JOIN attribute_combination ON attribute_combination.product_sale_elements_id = product_sale_elements.id
                    LEFT JOIN attribute_av_i18n ON attribute_av_i18n.id = attribute_combination.attribute_av_id AND attribute_av_i18n.locale = :locale
                    LEFT JOIN product_price ON product_price.product_sale_elements_id = product_sale_elements.id
                    LEFT JOIN currency ON currency.id = product_price.currency_id';

        $stmt = $con->prepare($query);
        $stmt->bindValue('locale', $locale);
        $stmt->execute();

        return $this->getDataJsonCache($stmt, 'product_price');
    }
}
