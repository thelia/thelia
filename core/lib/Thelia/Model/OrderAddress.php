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
use Thelia\Model\Base\OrderAddress as BaseOrderAddress;
use Thelia\Model\Map\OrderAddressTableMap;

class OrderAddress extends BaseOrderAddress
{
    public function preUpdate(?ConnectionInterface $con = null): bool
    {
        $this->dropVatVerificationWhenTheNumberChanges();

        return parent::preUpdate($con);
    }

    private function dropVatVerificationWhenTheNumberChanges(): void
    {
        if (!$this->isColumnModified(OrderAddressTableMap::COL_VAT_NUMBER)
            || $this->isColumnModified(OrderAddressTableMap::COL_VAT_VERIFIED_AT)) {
            return;
        }

        $this
            ->setVatVerifiedAt(null)
            ->setVatVerifiedName(null);
    }
}
