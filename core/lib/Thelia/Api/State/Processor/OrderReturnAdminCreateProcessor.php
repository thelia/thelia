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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Service\OrderReturnHydrator;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Exception\ReturnRequestConflictException;
use Thelia\Domain\OrderReturn\Service\OrderReturnStatusEmailDispatcher;
use Thelia\Domain\OrderReturn\Service\OrderReturnWriteTransactionInterface;
use Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope;
use Thelia\Model\Customer;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturn as OrderReturnModel;

/**
 * A return opened by the merchant from the back-office, without a customer
 * request (a return received by phone). The customer is taken from the order,
 * the return is flagged as admin-created, and the lines are still checked.
 *
 * A request the database gave up on to break a lock conflict with another one
 * is answered 409: nothing was written, and it may be sent again.
 */
final readonly class OrderReturnAdminCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private OrderReturnHydrator $hydrator,
        private OrderReturnStatusEmailDispatcher $statusEmailDispatcher,
        private AdminSaleUnitScope $adminSaleUnitScope,
        private OrderReturnWriteTransactionInterface $transaction,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrderReturnResource) {
            return $this->dispatchStatusEmail(
                $this->persistProcessor->process($data, $operation, $uriVariables, $context),
            );
        }

        try {
            OrderReturnHydrator::assertNewLines($data);

            [$orderId, $orderProductIds] = OrderReturnHydrator::rowsToLock($data);
        } catch (ReturnNotAllowedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        $order = OrderQuery::create()->findPk($orderId);
        $customer = null !== $order && $this->adminSaleUnitScope->allows($order->getSaleUnitId()) ? $order->getCustomer() : null;

        if (!$customer instanceof Customer) {
            throw new UnprocessableEntityHttpException('The order does not exist or has no customer.');
        }

        // The merchant path holds the same lines as the customer one, so it
        // takes the same row locks, before anything is read, in the same
        // transaction as the insert they protect. The persist processor opens a
        // transaction of its own, which Propel nests inside this one.
        try {
            $result = $this->transaction->run($orderId, $orderProductIds, function () use ($data, $customer, $operation, $uriVariables, $context): mixed {
                $this->hydrator->hydrate($data, $customer, true);

                return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            });
        } catch (ReturnRequestConflictException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        } catch (ReturnNotAllowedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
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
            $this->statusEmailDispatcher->dispatchFor($model);
        }

        return $result;
    }
}
