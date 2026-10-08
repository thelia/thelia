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
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\OrderPaymentCapture;
use Thelia\Api\Resource\OrderPaymentTransaction;
use Thelia\Core\Event\Order\OrderPaymentCaptureEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Domain\Payment\Service\PaymentAmount;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderQuery;

/**
 * Captures through ORDER_PAYMENT_CAPTURE, the way the back office does, so a module
 * listening to that event sees both.
 *
 * A capture is not idempotent by nature: the provider takes the money each time it is
 * asked. The same amount asked again on the same order within a minute is answered 409
 * rather than taken twice — a client that retried a timed-out call gets told the first
 * one went through, and reads the journal.
 */
final readonly class OrderPaymentCaptureProcessor implements ProcessorInterface
{
    private const int REPEAT_WINDOW_SECONDS = 60;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderPaymentTransaction
    {
        if (!$data instanceof OrderPaymentCapture) {
            throw new \LogicException(\sprintf('Expected a %s, got %s.', OrderPaymentCapture::class, get_debug_type($data)));
        }

        $order = $this->order($uriVariables);

        if ($this->wasJustCaptured($order, $data->amount)) {
            throw new ConflictHttpException('The same amount was captured on this order a moment ago. Read the payment journal before asking again.');
        }

        $event = new OrderPaymentCaptureEvent($order, $data->amount);

        try {
            $this->eventDispatcher->dispatch($event, TheliaEvents::ORDER_PAYMENT_CAPTURE);
        } catch (PaymentException $exception) {
            // Nothing to capture, a module that takes the price at once, an amount the
            // authorization does not hold: the request is well formed and the shop
            // refuses it, which is what 422 says.
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->transformer->modelToResource(
            resourceClass: OrderPaymentTransaction::class,
            propelModel: $event->getTransaction(),
            context: $context,
        );
    }

    private function order(array $uriVariables): Order
    {
        $orderId = filter_var($uriVariables['orderId'] ?? null, \FILTER_VALIDATE_INT);
        $order = false === $orderId || $orderId < 1 ? null : OrderQuery::create()->findPk($orderId);

        if (!$order instanceof Order) {
            throw new NotFoundHttpException('No such order.');
        }

        return $order;
    }

    /**
     * Whether a capture of the same amount — or of the whole remainder, when no amount
     * is given twice — was written on this order within the last minute and did not fail.
     */
    private function wasJustCaptured(Order $order, ?float $amount): bool
    {
        $latest = OrderPaymentTransactionQuery::create()
            ->filterByOrderId((int) $order->getId())
            ->filterByTypeEnum(PaymentTransactionType::CAPTURE)
            ->orderById(Criteria::DESC)
            ->findOne();

        if (null === $latest || $latest->isFailed()) {
            return false;
        }

        $createdAt = $latest->getCreatedAt();

        if (!$createdAt instanceof \DateTimeInterface || time() - $createdAt->getTimestamp() > self::REPEAT_WINDOW_SECONDS) {
            return false;
        }

        // Without an amount the caller asks for the remainder, and after the first
        // capture the remainder changed: the recorder settles that case by refusing a
        // capture of nothing. The repeat is the explicit same figure.
        return null !== $amount && 0 === PaymentAmount::compare($amount, (string) $latest->getAmount());
    }
}
