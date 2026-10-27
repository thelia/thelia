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

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupQuery;

/**
 * Which payment modules a customer group may pay with.
 *
 * A group is either restricted (customer_group.restricts_payment_modules, set by the writer of
 * its list alone) or not. A group that is not may pay with every module. A restricted group
 * may pay with the modules it lists (customer_group_payment_module) and no other one, and its
 * list can be empty: deleting the one module it listed cascades on the list and leaves the
 * flag, so the group can then pay with nothing, instead of with everything. The list of one
 * group never opens nor closes anything to another group. The
 * one place that says so: the options offered at checkout (API and loop) and the guards
 * that hold when the order is placed all ask it, by an explicit call. It cannot ride on the
 * MODULE_PAYMENT_IS_VALID listeners: Action\Payment stops judging as soon as a module stops
 * the propagation of the event, so a listener of low priority could be skipped.
 *
 * The customer is the one the cart belongs to, never the session: the front API is
 * stateless, and the cart is what the order is built from. A cart with no customer is
 * a visitor's, who belongs to the default group.
 *
 * The lists are read once for the request, in one statement, whatever the number of modules
 * and customers asked about.
 */
final class CustomerGroupPaymentPolicy implements ResetInterface
{
    /** @var array<int, list<int>>|null ids of the modules each restricted group may pay with, by group id; a restricted group whose list is empty is there with an empty list */
    private ?array $moduleIdsByGroup = null;

    public function __construct(
        private readonly CustomerGroupResolver $customerGroupResolver,
    ) {
    }

    public function isAllowed(int $moduleId, ?Customer $customer): bool
    {
        $moduleIdsByGroup = $this->moduleIdsByGroup ??= $this->load();

        // Settled before the customer is read: a shop where no group is restricted never asks
        // which group the visitor belongs to.
        if ([] === $moduleIdsByGroup) {
            return true;
        }

        $allowedModuleIds = $moduleIdsByGroup[$this->customerGroupResolver->groupIdOf($customer)] ?? null;

        return null === $allowedModuleIds || \in_array($moduleId, $allowedModuleIds, true);
    }

    public function isAllowedForCart(int $moduleId, Cart $cart): bool
    {
        return $this->isAllowed($moduleId, $cart->getCustomer());
    }

    /**
     * The refusal of the guards that hold when an order is placed or paid again.
     *
     * @throws InvalidPaymentException when the group of the customer of the cart does not list the module
     */
    public function refuseUnlessAllowedForCart(int $moduleId, Cart $cart): void
    {
        $this->refuseUnlessAllowed($moduleId, $cart->getCustomer());
    }

    /**
     * The same refusal for a caller that holds the customer rather than the cart, such as the
     * settlement of an order, which is made for the customer the order names.
     *
     * @throws InvalidPaymentException when the group of the customer does not list the module
     */
    public function refuseUnlessAllowed(int $moduleId, ?Customer $customer): void
    {
        if (!$this->isAllowed($moduleId, $customer)) {
            throw new InvalidPaymentException('Payment module is not available');
        }
    }

    public function reset(): void
    {
        $this->moduleIdsByGroup = null;
    }

    /**
     * @return array<int, list<int>>
     */
    private function load(): array
    {
        $moduleIdsByGroup = [];

        // One statement: a restricted group with no link left comes back once, with no module.
        $rows = CustomerGroupQuery::create()
            ->filterByRestrictsPaymentModules(1)
            ->leftJoinCustomerGroupPaymentModule()
            ->select(['Id', 'CustomerGroupPaymentModule.ModuleId'])
            ->find();

        foreach ($rows as $row) {
            $moduleIdsByGroup[(int) $row['Id']] ??= [];

            if (null !== $row['CustomerGroupPaymentModule.ModuleId']) {
                $moduleIdsByGroup[(int) $row['Id']][] = (int) $row['CustomerGroupPaymentModule.ModuleId'];
            }
        }

        return $moduleIdsByGroup;
    }
}
