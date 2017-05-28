<?php

namespace Thelia\Model;

use Thelia\Model\Base\FeatureProductQuery as BaseFeatureProductQuery;
use Thelia\Log\Tlog;

/**
 * Skeleton subclass for performing query and update operations on the 'feature_product' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class FeatureProductQuery extends BaseFeatureProductQuery
{
    /**
     * @deprecated
     */
    public function filterByFreeTextValue($freeTextValue = null, $comparison = null)
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
        return parent::filterByFreeTextValue($freeTextValue, $comparison)
    }
}
// FeatureProductQuery
