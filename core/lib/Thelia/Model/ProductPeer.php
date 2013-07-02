<?php

namespace Thelia\Model;

use Thelia\Model\om\BaseProductPeer;


/**
 * Skeleton subclass for performing query and update operations on the 'product' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Thelia.Model
 */
class ProductPeer extends BaseProductPeer
{
    public static function getPriceDependingOnPromoExpression()
    {
        return 'IF(' . self::PROMO . '=1, ' . self::PRICE2 . ', ' . self::PRICE . ')';
    }
}
