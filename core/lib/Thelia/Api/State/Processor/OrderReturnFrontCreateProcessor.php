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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Service\OrderReturnHydrator;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\ReturnRequestLimiter;
use Thelia\Model\Customer;
use Thelia\Model\OrderReturn as OrderReturnModel;

/**
 * A return opened under /front/account belongs to the customer holding the
 * token. The owner and the initial status never come from the body, and every
 * line is checked against the order before anything is written.
 */
final readonly class OrderReturnFrontCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private TokenStorageInterface $tokenStorage,
        private OrderReturnHydrator $hydrator,
        private EventDispatcherInterface $eventDispatcher,
        private ReturnRequestLimiter $limiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrderReturnResource) {
            return $this->dispatchStatusEmail(
                $this->persistProcessor->process($data, $operation, $uriVariables, $context),
            );
        }

        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer) {
            throw new AccessDeniedHttpException('A customer must be authenticated to open a return.');
        }

        if (!$this->limiter->allows($customer)) {
            throw new TooManyRequestsHttpException(message: 'Too many return requests, please try again later.');
        }

        // Checking how much of a line is still returnable and writing the
        // return that consumes it belong to the same transaction, or two
        // requests arriving together are both allowed the same last unit. The
        // persist processor opens a transaction of its own, which Propel nests
        // inside this one.
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();

        try {
            $this->hydrator->hydrate($data, $customer, false);
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
