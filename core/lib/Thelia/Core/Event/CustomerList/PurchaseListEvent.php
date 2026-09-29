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

namespace Thelia\Core\Event\CustomerList;

use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;

/**
 * A write on a purchase list, dispatched by PurchaseListFacade once it has
 * checked who may write what.
 *
 * On creation the list is attached by the action. On update, a null title
 * keeps the current one and null lines keep the current lines; non-null lines
 * replace them all.
 */
class PurchaseListEvent extends ActionEvent
{
    public function __construct(
        private readonly Customer $customer,
        private ?CustomerList $customerList = null,
        private readonly ?string $title = null,
        private readonly ?ReferenceQuantityLines $lines = null,
    ) {
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getCustomerList(): ?CustomerList
    {
        return $this->customerList;
    }

    public function setCustomerList(CustomerList $customerList): self
    {
        $this->customerList = $customerList;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getLines(): ?ReferenceQuantityLines
    {
        return $this->lines;
    }
}
