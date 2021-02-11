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
use Thelia\Model\Base\BrandI18n as BaseBrandI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class BrandI18n extends BaseBrandI18n
{
    use I18nTimestampableTrait;

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $brand = $this->getBrand();

        $brand->generateRewrittenUrl($this->getLocale(), $con);
    }
}
