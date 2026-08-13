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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\Address;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Model\Customer;

/**
 * An address written under /front/account belongs to the customer holding the
 * token. The owner never comes from the body: a caller sending someone else's
 * customer would otherwise write into that customer's address book, and a
 * caller sending none would reach the database with no owner at all.
 */
final readonly class CustomerAddressProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $customer = $this->tokenStorage->getToken()?->getUser();

        if ($data instanceof Address && $customer instanceof Customer) {
            $data->setCustomer((new CustomerResource())->setId($customer->getId()));
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
