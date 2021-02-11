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

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\ProductI18n as BaseProductI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class ProductI18n extends BaseProductI18n
{
    use I18nTimestampableTrait;

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $product = $this->getProduct();
        $product->generateRewrittenUrl($this->getLocale(), $con);
    }
}
