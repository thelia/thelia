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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\OrderPaymentCapture;
use Thelia\Api\Resource\OrderPaymentTransaction;
use Thelia\Core\Event\Order\OrderPaymentCaptureEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Exception\DuplicateCaptureException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Domain\Payment\Exception\PaymentJournalBusyException;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

/**
 * Captures through ORDER_PAYMENT_CAPTURE, the way the back office does, so a module
 * listening to that event sees both, and both share the guards of the capture service:
 * a capture waiting for its answer reserves what it asked for, and the same amount asked
 * again within a minute is a repetition, answered 409 rather than taken twice.
 */
final readonly class OrderPaymentCaptureProcessor implements ProcessorInterface
{
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

        $event = new OrderPaymentCaptureEvent($this->order($uriVariables), $data->amount);

        try {
            $this->eventDispatcher->dispatch($event, TheliaEvents::ORDER_PAYMENT_CAPTURE);
        } catch (DuplicateCaptureException|PaymentJournalBusyException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        } catch (PaymentException $exception) {
            // Nothing to capture, a module that takes the price at once, an amount the
            // authorization does not hold, a refusal of the provider: the request is well
            // formed and the shop refuses it, which is what 422 says.
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (\Throwable $exception) {
            // The module could not reach the provider: the capture stays pending in the
            // journal until the provider confirms it. The technical message is logged by
            // the capture service, not sent back.
            throw new HttpException(502, 'The payment provider did not answer. The capture stays pending in the payment journal until the provider confirms it.', $exception);
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
}
