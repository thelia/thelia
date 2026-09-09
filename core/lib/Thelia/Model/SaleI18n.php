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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Base\SaleI18n as BaseSaleI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class SaleI18n extends BaseSaleI18n
{
    use I18nTimestampableTrait;

    /**
     * The address of an operation is built from its title, so it can only be
     * generated once the translation carrying that title exists — one per locale,
     * exactly as ProductI18n does it.
     *
     * @throws PropelException
     */
    public function postInsert(?ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        $this->getSale()->generateRewrittenUrl($this->getLocale(), $con);
    }
}
