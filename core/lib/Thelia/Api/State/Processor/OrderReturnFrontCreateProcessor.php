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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Service\OrderReturnHydrator;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Exception\ReturnRequestConflictException;
use Thelia\Domain\OrderReturn\Service\OrderReturnHistoryRecorder;
use Thelia\Domain\OrderReturn\Service\OrderReturnStatusEmailDispatcher;
use Thelia\Domain\OrderReturn\Service\OrderReturnWriteTransactionInterface;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Domain\OrderReturn\Service\ReturnRequestLimiter;
use Thelia\Model\Customer;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturn as OrderReturnModel;

/**
 * A return opened under /front/account belongs to the customer holding the
 * token. The owner and the initial status never come from the body, and every
 * line is checked against the order before anything is written.
 *
 * A request the database gave up on to break a lock conflict with another one
 * is answered 409: nothing was written, and it may be sent again.
 */
final readonly class OrderReturnFrontCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private TokenStorageInterface $tokenStorage,
        private OrderReturnHydrator $hydrator,
        private OrderReturnStatusEmailDispatcher $statusEmailDispatcher,
        private ReturnRequestLimiter $limiter,
        private OrderReturnWriteTransactionInterface $transaction,
        private OrderReturnHistoryRecorder $historyRecorder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrderReturnResource) {
            return $this->announceCreation(
                $this->persistProcessor->process($data, $operation, $uriVariables, $context),
            );
        }

        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer) {
            throw new AccessDeniedHttpException('A customer must be authenticated to open a return.');
        }

        try {
            OrderReturnHydrator::assertNewLines($data);

            [$orderId, $orderProductIds] = OrderReturnHydrator::rowsToLock($data);

            // Before any lock: naming somebody else's order in the body must not
            // let a caller hold it, even for the length of a refusal.
            $this->assertOrderOfTheCustomer($orderId, $customer);

            // Checking how much of a line is still returnable and writing the
            // return that consumes it belong to the same transaction, the rows
            // locked before anything is read, or two requests arriving together
            // are both allowed the same last unit. The persist processor opens a
            // transaction of its own, which Propel nests inside this one.
            $result = $this->transaction->run($orderId, $orderProductIds, function () use ($data, $customer, $operation, $uriVariables, $context): mixed {
                $this->hydrator->hydrate($data, $customer, false);

                // The quota counts the returns a customer opens, not the mistakes
                // they make filling the form in: consumed before the eligibility
                // gate, twenty refusals closed the hour for the one valid request
                // that followed them.
                if (!$this->limiter->allows($customer)) {
                    throw new TooManyRequestsHttpException(message: 'Too many return requests, please try again later.');
                }

                return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            });
        } catch (ReturnRequestConflictException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        } catch (ReturnNotAllowedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->announceCreation($result);
    }

    /**
     * A plain read, outside any transaction, so it takes no lock and fixes no
     * picture.
     *
     * @throws ReturnNotAllowedException
     */
    private function assertOrderOfTheCustomer(int $orderId, Customer $customer): void
    {
        $ownerId = OrderQuery::create()->filterById($orderId)->select(['CustomerId'])->findOne();

        // A missing order is refused like a foreign one, with the same status and
        // message, as FrontOrderReturnRelationDenormalizer does for a missing IRI.
        if (null === $ownerId || (int) $ownerId !== (int) $customer->getId()) {
            throw new ReturnNotAllowedException(ReturnEligibilityChecker::ORDER_NOT_USABLE_MESSAGE);
        }
    }

    /**
     * Announces the creation of the return, once it is committed and never from
     * inside the transaction: first the line that opens the return on the
     * history of its order, then the mail to the customer, so the timeline
     * shows the gesture before the mail that follows it. A mail is not
     * something a rollback takes back, and a line written inside the
     * transaction would go with a rollback.
     * ORDER_RETURN_CREATE is not dispatched here, which is what would have
     * written the line and sent the mail.
     */
    private function announceCreation(mixed $result): mixed
    {
        $model = $result instanceof OrderReturnResource ? $result->getPropelModel() : null;
        if ($model instanceof OrderReturnModel) {
            $this->historyRecorder->recordOpened($model);
            $this->statusEmailDispatcher->dispatchFor($model);
        }

        return $result;
    }
}
