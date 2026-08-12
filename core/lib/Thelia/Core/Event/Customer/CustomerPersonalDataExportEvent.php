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

namespace Thelia\Core\Event\Customer;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Customer;

/**
 * Collects everything the shop knows about one person.
 *
 * Dispatched with TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT. Core fills the
 * event with the account, its addresses, orders, carts and newsletter
 * subscription; listeners registered after it may add their own sections.
 */
class CustomerPersonalDataExportEvent extends ActionEvent
{
    /** @var array<string, mixed> */
    private array $personalData = [];

    public function __construct(private readonly Customer $customer)
    {
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPersonalData(): array
    {
        return $this->personalData;
    }

    /**
     * @param array<string, mixed> $personalData
     *
     * @return $this
     */
    public function setPersonalData(array $personalData): static
    {
        $this->personalData = $personalData;

        return $this;
    }

    /**
     * @return $this
     */
    public function addSection(string $sectionName, mixed $data): static
    {
        $this->personalData[$sectionName] = $data;

        return $this;
    }
}
