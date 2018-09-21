<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\ImportExport\Export\Type;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;


class ProductI18nExport extends AbstractExport
{
    const FILE_NAME = 'product_i18n';

    protected $orderAndAliases = [
        ProductTableMap::REF => 'ref',
        'product_i18n_TITLE' => 'product_title',
        'product_i18n_CHAPO' => 'product_chapo',
        'product_i18n_DESCRIPTION' => 'product_description',
        'product_i18n_POSTSCRIPTUM' => 'product_postscriptum',
    ];

    protected $idxStripHtml = [
        'product_i18n_CHAPO',
        'product_i18n_DESCRIPTION',
        'product_i18n_POSTSCRIPTUM',
    ];

    /**
     * @return array|Criteria|\Propel\Runtime\ActiveQuery\ModelCriteria
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getData()
    {
        $locale = $this->language->getLocale();

        $productJoin = new Join(
            ProductTableMap::ID,
            ProductI18nTableMap::ID,
            Criteria::LEFT_JOIN
        );

        $query = ProductQuery::create()
            ->addSelfSelectColumns()
            ->addJoinObject($productJoin, 'product_join')
            ->addJoinCondition(
                'product_join',
                ProductI18nTableMap::LOCALE . ' = ?', $locale,
                null,
                \PDO::PARAM_STR
            )
            ->addAsColumn('product_i18n_TITLE', ProductI18nTableMap::TITLE)
            ->addAsColumn('product_i18n_CHAPO', ProductI18nTableMap::CHAPO)
            ->addAsColumn('product_i18n_DESCRIPTION', ProductI18nTableMap::DESCRIPTION)
            ->addAsColumn('product_i18n_POSTSCRIPTUM', ProductI18nTableMap::POSTSCRIPTUM);

        return $query;
    }

    /**
     * @param array $data
     * @return array
     */
    public function beforeSerialize(array $data)
    {
        foreach ($data as $idx => &$value) {
            if (in_array($idx, $this->idxStripHtml) && !empty($value)) {
                $value = strip_tags($value);

                $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return parent::beforeSerialize($data);
    }


}