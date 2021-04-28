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

use Thelia\Model\Base\FeatureProduct as BaseFeatureProduct;

class FeatureProduct extends BaseFeatureProduct
{
    /**
     * {@inheritdoc}
     *
     * @deprecated since version 2.4.0, to be removed in 3.0.
     *                      Please use  setIsFreeText() instead
     */
    public function setFreeTextValue($v)
    {
        $this->setIsFreeText($v ? true : false);

        return parent::setFreeTextValue($v);
    }

    /**
     * {@inheritDoc}
     */
    public function setIsFreeText($v)
    {
        parent::setFreeTextValue($v ? 1 : null); //for preventing log deprecation and infinity recursion

        return parent::setIsFreeText($v);
    }
}
