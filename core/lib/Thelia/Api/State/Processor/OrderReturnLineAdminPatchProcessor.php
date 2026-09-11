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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\OrderReturnLine as OrderReturnLineResource;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderReturnLineQuery;

/**
 * The two quantities of a return line are what the rest of the feature counts
 * on, and the admin patch is the only way to change them.
 *
 * `quantityReceived` is what the reception restocks and what the refundable
 * amount is recomputed from, so it may never exceed what the customer asked
 * for: a merchant typing 20 instead of 2 would put eighteen units the shop
 * never got back into the stock, and refund them.
 *
 * `quantity` is the size of the return itself. Raising it is opening a bigger
 * return, and it goes through the same gate the creation does - under the same
 * row lock, in the same transaction - with the line being edited left out of
 * the cumulative count.
 */
final readonly class OrderReturnLineAdminPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private ReturnEligibilityChecker $eligibility,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrderReturnLineResource) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $stored = OrderReturnLineQuery::create()->findPk((int) ($uriVariables['id'] ?? 0));

        if (null === $stored) {
            throw new NotFoundHttpException('Return line not found.');
        }

        // A merge patch carries only the fields it changes; the others come
        // back from the stored line, and an uninitialized typed property means
        // the read did not fill it.
        $quantity = isset($data->quantity) ? $data->getQuantity() : (float) $stored->getQuantity();
        $received = $data->getQuantityReceived() ?? 0.0;

        if ($received < 0) {
            throw new UnprocessableEntityHttpException('The received quantity cannot be negative.');
        }

        if ($received > $quantity + ReturnEligibilityChecker::QUANTITY_TOLERANCE) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'The received quantity (%s) cannot exceed the returned quantity (%s) of this line.',
                $received,
                $quantity,
            ));
        }

        if (abs($quantity - (float) $stored->getQuantity()) <= ReturnEligibilityChecker::QUANTITY_TOLERANCE) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $orderProduct = $stored->getOrderProduct();
        $order = $orderProduct?->getOrder();
        $customer = $order?->getCustomer();

        if (!$order instanceof Order || !$customer instanceof Customer) {
            throw new UnprocessableEntityHttpException('The return line is not attached to an order of a customer.');
        }

        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();

        try {
            $this->eligibility->lockLine($orderProduct);
            $this->eligibility->assertReturnable(
                $order,
                $customer,
                $orderProduct,
                $quantity,
                excludeReturnId: (int) $stored->getOrderReturnId(),
            );

            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            $connection->commit();
        } catch (ReturnNotAllowedException $exception) {
            $connection->rollBack();

            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $result;
    }
}
