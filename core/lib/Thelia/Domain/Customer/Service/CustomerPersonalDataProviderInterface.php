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

namespace Thelia\Domain\Customer\Service;

use Thelia\Model\Customer;

/**
 * Declares the personal data a module holds about a customer.
 *
 * Implementations are auto-registered: any service implementing this
 * interface is tagged `thelia.customer.personal_data_provider` and is called
 * by CustomerPersonalDataExporter when the data of a customer is exported,
 * and by CustomerAnonymizer when that customer is anonymized.
 *
 * Core covers the customer account, its addresses, orders and their frozen
 * order addresses, carts and newsletter subscriptions. Everything else — a
 * loyalty balance, a support ticket, a stored payment token — belongs to the
 * module that stores it, hence this interface.
 */
interface CustomerPersonalDataProviderInterface
{
    /**
     * Key under which this provider appears in the exported data.
     *
     * It must not collide with a core section (customer, addresses, orders,
     * carts, newsletter) nor with another provider: prefer the module code,
     * for instance `loyalty` or `virtual_product_delivery`.
     */
    public function getPersonalDataSectionName(): string;

    /**
     * Personal data held about this customer, in a structure suitable for
     * JSON serialization. Return an empty array when there is nothing.
     *
     * @return array<int|string, mixed>
     */
    public function exportPersonalData(Customer $customer): array;

    /**
     * Neutralizes or deletes the personal data held about this customer.
     *
     * Runs inside the anonymization transaction: throwing rolls back the
     * whole operation, including what core already anonymized.
     */
    public function anonymizePersonalData(Customer $customer): void;
}
