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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Service\OrderReturnHydrator;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Model\Customer;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturn as OrderReturnModel;

/**
 * A return opened by the merchant from the back-office, without a customer
 * request (a return received by phone). The customer is taken from the order,
 * the return is flagged as admin-created, and the lines are still checked.
 */
final readonly class OrderReturnAdminCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private OrderReturnHydrator $hydrator,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof OrderReturnResource) {
            $order = OrderQuery::create()->findPk($data->getOrder()->getId());
            $customer = $order?->getCustomer();

            if ($customer instanceof Customer) {
                try {
                    $this->hydrator->hydrate($data, $customer, true);
                } catch (ReturnNotAllowedException $exception) {
                    throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
                }
            }
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $model = $result instanceof OrderReturnResource ? $result->getPropelModel() : null;
        if ($model instanceof OrderReturnModel) {
            $this->eventDispatcher->dispatch(new OrderReturnEvent($model), TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL);
        }

        return $result;
    }
}
