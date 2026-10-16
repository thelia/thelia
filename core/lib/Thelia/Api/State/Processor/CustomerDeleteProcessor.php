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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Exception\CustomerException;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\Event\CustomerEvent;

/**
 * Deletes a customer the way the back office does, through
 * TheliaEvents::CUSTOMER_DELETEACCOUNT, never through the generic Propel remove
 * processor: the action refuses a customer who has orders, and tells the payment
 * providers to forget the customer's stored methods before the foreign key cascade
 * drops their rows. Deleting the row alone left those methods alive at the provider.
 *
 * The refusal (CustomerException) answers 409: the request is valid, the state of
 * the customer is what forbids it.
 */
final readonly class CustomerDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof CustomerResource) {
            throw new \LogicException(\sprintf('%s only deletes customers.', self::class));
        }

        $customer = $data->getPropelModel();

        if (!$customer instanceof CustomerModel) {
            throw new \LogicException(\sprintf('The model of %s is not a customer.', $data::class));
        }

        try {
            $this->eventDispatcher->dispatch(new CustomerEvent($customer), TheliaEvents::CUSTOMER_DELETEACCOUNT);
        } catch (CustomerException $refusal) {
            throw new ConflictHttpException($refusal->getMessage(), $refusal);
        }

        // The addons run once the customer is gone, not inside the action's
        // transaction: the action calls the providers after its own commit, and an
        // enclosing transaction would make that commit a nested one. A failure
        // here leaves addon rows behind rather than addon data lost on a customer
        // the action refused to delete.
        foreach ($this->apiResourcePropelTransformerService->getResourceAddonDefinitions($data::class) as $addonClass) {
            if (is_subclass_of($addonClass, ResourceAddonInterface::class)) {
                (new $addonClass())
                    ->setContext($context)
                    ->doDelete($customer, $data);
            }
        }

        $this->eventDispatcher->dispatch(new ResourcePersistedEvent($data::class, $customer, ResourcePersistedEvent::OPERATION_DELETE));

        return null;
    }
}
