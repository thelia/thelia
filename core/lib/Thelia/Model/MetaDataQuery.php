<?php

namespace Thelia\Model;

use Thelia\Model\Base\MetaDataQuery as BaseMetaDataQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'meta_data' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class MetaDataQuery extends BaseMetaDataQuery
{

    /**
     *
     * @param string $elementKey the element Key : product, category, ...
     * @param int    $elementId  the element id
     *
     * @return array all meta data affected to this element
     */
    public static function getAllValues($elementKey, $elementId)
    {

        $out = array();

        if (is_int($elementId)) {
            $datas = self::create()
                ->filterByElementKey($elementKey)
                ->filterByElementId($elementId)
                ->find();
            if (null !== $datas) {
                /** @var MetaData $data */
                foreach ($datas as $data) {
                    $out[$data->getMetaKey()] = $data->getValue();
                }
            }
        }

        return $out;
    }

} // MetaDataQuery
