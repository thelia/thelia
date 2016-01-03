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
     * @param string $metaKey    the meta Key
     * @param string $elementKey the element Key : product, category, ...
     * @param int    $elementId  the element id
     *
     * @return mixed the value affected to this element
     */
    public static function getVal($metaKey, $elementKey, $elementId, $default = null)
    {
        $out = null;

        $data = self::create()
            ->filterByMetaKey($metaKey)
            ->filterByElementKey($elementKey)
            ->filterByElementId($elementId)
            ->findOne();

        if (null !== $data) {
            /** @var MetaData $data */
            $out = $data->getValue();
        } else {
            $out = $default;
        }

        return $out;
    }

    /**
     *
     * @param string $elementKey the element Key : product, category, ...
     * @param int    $elementId  the element id
     *
     * @return array all meta data affected to this element
     */
    public static function getAllVal($elementKey, $elementId)
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

    /**
     * Add or update the MetaData element
     *
     * @param string $metaKey    the meta Key
     * @param string $elementKey the element Key : product, category, ...
     * @param int    $elementId  the element id
     */
    public static function setVal($metaKey, $elementKey, $elementId, $value)
    {
        $data = self::create()
            ->filterByMetaKey($metaKey)
            ->filterByElementKey($elementKey)
            ->filterByElementId($elementId)
            ->findOne()
        ;

        if (null === $data) {
            $data = new MetaData();
            $data->setMetaKey($metaKey);
            $data->setElementKey($elementKey);
            $data->setElementId($elementId);
        }

        $data->setValue($value);

        $data->save();
    }
}
// MetaDataQuery
