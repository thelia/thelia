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

namespace Thelia\ImportExport\Export\Type;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;
use Thelia\Model\Product;

/**
 * Class ProductSEOExport.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class ProductSEOExport extends JsonFileAbstractExport
{
    public const FILE_NAME = 'product_seo';

    protected array $orderAndAliases = [
        'product_id' => 'id',
        'product_ref' => 'ref',
        'product_i18n_title' => 'product_title',
        'product_visible' => 'visible',
        'rewriting_url_url' => 'url',
        'product_i18n_meta_title' => 'page_title',
        'product_i18n_meta_description' => 'meta_description',
        'product_i18n_meta_keywords' => 'meta_keywords',
    ];

    protected function getData(): array|string|ModelCriteria
    {
        $locale = $this->language->getLocale();

        $con = Propel::getConnection();
        $query = 'SELECT
                        product.id as "product_id",
                        product.ref as "product_ref",
                        product_i18n.title as "product_i18n_title",
                        product.visible as "product_visible",
                        rewriting_url.url as "rewriting_url_url",
                        product_i18n.meta_title as "product_i18n_meta_title",
                        product_i18n.meta_description as "product_i18n_meta_description",
                        product_i18n.meta_keywords as "product_i18n_meta_keywords"
                    FROM product
                    LEFT JOIN product_i18n ON product_i18n.id = product.id AND product_i18n.locale = :locale
                    LEFT JOIN rewriting_url ON rewriting_url.view = "'.(new Product())->getRewrittenUrlViewName().'" AND rewriting_url.view_id = product.id
                    ORDER BY product.id';
        $stmt = $con->prepare($query);
        $stmt->bindValue('locale', $locale);
        $stmt->execute();

        return $this->getDataJsonCache($stmt, 'product_seo');
    }
}
