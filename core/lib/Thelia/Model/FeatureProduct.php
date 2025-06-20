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
namespace Thelia\Model;

use Thelia\Log\Tlog;
use Thelia\Model\Base\FeatureProduct as BaseFeatureProduct;

class FeatureProduct extends BaseFeatureProduct
{
    /**
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  getIsFreeText() instead
     */
    public function getFreeTextValue()
    {
        $bt = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));

        return parent::getFreeTextValue();
    }

    /**
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  setIsFreeText() instead
     */
    public function setFreeTextValue($v)
    {
        $bt = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        Tlog::getInstance()->warning(sprintf('Using `free_text_value` is deprecated. Use `is_free_text` instead. Used in %s:%d', $bt[0]['file'], $bt[0]['line']));
        $this->setIsFreeText((bool) $v);

        return parent::setFreeTextValue($v);
    }

    public function setIsFreeText($v)
    {
        parent::setFreeTextValue($v ? 1 : null); // for preventing log deprecation and infinity recursion

        return parent::setIsFreeText($v);
    }
}
