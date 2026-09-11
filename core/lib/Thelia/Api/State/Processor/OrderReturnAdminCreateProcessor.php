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
use Propel\Runtime\Propel;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Service\OrderReturnHydrator;
use Thelia\Config\DatabaseConfiguration;
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
        if (!$data instanceof OrderReturnResource) {
            return $this->dispatchStatusEmail(
                $this->persistProcessor->process($data, $operation, $uriVariables, $context),
            );
        }

        $order = OrderQuery::create()->findPk($data->getOrder()->getId());
        $customer = $order?->getCustomer();

        if (!$customer instanceof Customer) {
            throw new UnprocessableEntityHttpException('The order does not exist or has no customer.');
        }

        // The merchant path holds the same lines as the customer one, so it
        // takes the same row locks, in the same transaction as the insert they
        // protect. The persist processor opens a transaction of its own, which
        // Propel nests inside this one.
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();

        try {
            $this->hydrator->hydrate($data, $customer, true);
            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            $connection->commit();
        } catch (ReturnNotAllowedException $exception) {
            $connection->rollBack();

            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $this->dispatchStatusEmail($result);
    }

    /**
     * Announces the return to the customer once it is committed, never from
     * inside the transaction: a mail is not something a rollback takes back.
     */
    private function dispatchStatusEmail(mixed $result): mixed
    {
        $model = $result instanceof OrderReturnResource ? $result->getPropelModel() : null;
        if ($model instanceof OrderReturnModel) {
            $this->eventDispatcher->dispatch(new OrderReturnEvent($model), TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL);
        }

        return $result;
    }
}
