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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Log\Tlog;
use Thelia\Model\Base\FeatureProductQuery as BaseFeatureProductQuery;

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
     * @inheritdoc
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  filterByIsFreeText() instead
     */
    public function filterByFreeTextValue($freeTextValue = null, $comparison = null)
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
        return parent::filterByFreeTextValue($freeTextValue, $comparison);
    }

    /**
     * @inheritdoc
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  orderByIsFreeText() instead
     */
    public function orderByFreeTextValue($order = Criteria::ASC)
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
        return parent::orderByFreeTextValue($order);
    }

    /**
     * @inheritdoc
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  groupByIsFreeText() instead
     */
    public function groupByFreeTextValue()
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
        return parent::groupByFreeTextValue();
    }

    /**
     * @inheritdoc
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  findByIsFreeText() instead
     */
    public function findByFreeTextValue($free_text_value)
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
        return parent::findByFreeTextValue($free_text_value);
    }

    /**
     * @inheritdoc
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  findOneByIsFreeText() instead
     */
    public function findOneByFreeTextValue($free_text_value)
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
        return parent::findOneByFreeTextValue($free_text_value);
    }
}
// FeatureProductQuery
