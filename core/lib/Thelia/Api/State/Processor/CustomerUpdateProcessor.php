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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Domain\Customer\Service\CustomerGroupAssignment;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\CustomerQuery;

/**
 * Updates a customer through the generic persistence, then tells the shop when the write moved
 * the customer to another group, the way every other writer does
 * ({@see CustomerGroupAssignment}): the modules that follow the group of a customer hear of a
 * move made by the API as they hear of one made in the back office.
 */
final readonly class CustomerUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private CustomerGroupAssignment $customerGroupAssignment,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $previousCustomerGroupId = isset($uriVariables['id']) && $data instanceof CustomerResource
            ? $this->groupOf((int) $uriVariables['id'])
            : null;

        $saved = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if (null !== $previousCustomerGroupId && $saved instanceof CustomerResource) {
            $customer = $saved->getPropelModel();

            if ($customer instanceof CustomerModel) {
                $this->customerGroupAssignment->announceChangeFrom($customer, $previousCustomerGroupId);
            }
        }

        return $saved;
    }

    private function groupOf(int $customerId): ?int
    {
        $customerGroupId = CustomerQuery::create()
            ->filterById($customerId)
            ->select('CustomerGroupId')
            ->findOne();

        return null === $customerGroupId ? null : (int) $customerGroupId;
    }
}
