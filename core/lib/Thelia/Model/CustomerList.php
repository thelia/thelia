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

use Thelia\Domain\CustomerList\Enum\CustomerListType;
use Thelia\Model\Base\CustomerList as BaseCustomerList;

class CustomerList extends BaseCustomerList
{
    /**
     * The stored sort, as the enum the domain reads. An unknown value throws
     * rather than being taken for a purchase list.
     */
    public function getListType(): CustomerListType
    {
        return CustomerListType::from((string) $this->getType());
    }
}
