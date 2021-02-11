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

use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;

class ProductI18nExport extends JsonFileAbstractExport
{
    const FILE_NAME = 'product_i18n';

    protected $orderAndAliases = [
        'product_ref' => 'ref',
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

    protected function getData()
    {
        $locale = $this->language->getLocale();

        $con = Propel::getConnection();

        $query = 'SELECT
                        product.ref as product_ref,
                        product_i18n.title as "product_i18n_TITLE",
                        product_i18n.chapo as "product_i18n_CHAPO",
                        product_i18n.description as "product_i18n_DESCRIPTION",
                        product_i18n.postscriptum as "product_i18n_POSTSCRIPTUM"
                    FROM product
                    LEFT JOIN product_i18n ON (product_i18n.id = product.id AND product_i18n.locale = :locale)'
        ;

        $stmt = $con->prepare($query);
        $stmt->bindValue('locale', $locale);
        $stmt->execute();

        return $this->getDataJsonCache($stmt, 'product_i18n');
    }

    /**
     * @return array
     */
    public function beforeSerialize(array $data)
    {
        foreach ($data as $idx => &$value) {
            if (\in_array($idx, $this->idxStripHtml) && !empty($value)) {
                $value = strip_tags($value);

                $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return parent::beforeSerialize($data);
    }
}
