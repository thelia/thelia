<?php

namespace Thelia\Model;

use Thelia\Model\om\BaseConfigQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'config' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Thelia.Model
 */
class ConfigQuery extends BaseConfigQuery
{
    public static function read($search, $default = null)
    {
        $value = self::create()->findOneByName($search);

        return $value ? $value->getValue() : $default;
    }
}
