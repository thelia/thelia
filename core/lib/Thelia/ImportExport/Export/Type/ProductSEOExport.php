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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * Class ProductSEOExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ProductSEOExport extends AbstractExport
{
    const FILE_NAME = 'product_seo';

    protected $orderAndAliases = [
        ProductTableMap::COL_REF => 'ref',
        'product_i18n_TITLE' => 'product_title',
        ProductTableMap::COL_VISIBLE => 'visible',
        'product_URL' => 'url',
        'product_seo_TITLE' => 'page_title',
        'product_seo_META_DESCRIPTION' => 'meta_description',
        'product_seo_META_KEYWORDS' => 'meta_keywords',
    ];

    public function getData()
    {
        $locale = $this->language->getLocale();

        $urlJoin = new Join(ProductTableMap::COL_ID, RewritingUrlTableMap::COL_VIEW_ID, Criteria::LEFT_JOIN);
        $productJoin = new Join(ProductTableMap::COL_ID, ProductI18nTableMap::COL_ID, Criteria::LEFT_JOIN);

        $query = ProductQuery::create()
            ->addSelfSelectColumns()
            ->addJoinObject($urlJoin, 'rewriting_url_join')
            ->addJoinCondition(
                'rewriting_url_join',
                RewritingUrlTableMap::COL_VIEW_LOCALE . ' = ?',
                $locale,
                null,
                \PDO::PARAM_STR
            )
            ->addJoinCondition(
                'rewriting_url_join',
                RewritingUrlTableMap::COL_VIEW . ' = ?',
                (new Product())->getRewrittenUrlViewName(),
                null,
                \PDO::PARAM_STR
            )
            ->addJoinCondition('rewriting_url_join', 'ISNULL(' . RewritingUrlTableMap::COL_REDIRECTED . ')')
            ->addJoinObject($productJoin, 'product_join')
            ->addJoinCondition('product_join', ProductI18nTableMap::COL_LOCALE . ' = ?', $locale, null, \PDO::PARAM_STR)

            ->addAsColumn('product_i18n_TITLE', ProductI18nTableMap::COL_TITLE)
            ->addAsColumn('product_seo_TITLE', ProductI18nTableMap::COL_META_TITLE)
            ->addAsColumn('product_seo_META_DESCRIPTION', ProductI18nTableMap::COL_META_DESCRIPTION)
            ->addAsColumn('product_seo_META_KEYWORDS', ProductI18nTableMap::COL_META_KEYWORDS)
            ->addAsColumn('product_URL', RewritingUrlTableMap::COL_URL)
        ;

        return $query;
    }
}
