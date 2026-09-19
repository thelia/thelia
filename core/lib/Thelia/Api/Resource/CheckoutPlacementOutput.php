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

namespace Thelia\Api\Resource;

use Thelia\Domain\Checkout\DTO\CheckoutPlacementResult;

/**
 * The order the checkout produced, and what is left for the front to do.
 *
 * On a retry — `alreadyPlaced: true` — what is left is never a payment action. The payment
 * modules were called once and are deliberately not called again, so `paymentAction` comes
 * back as `none` even for an order that is still waiting for a gateway the first call
 * redirected to. The state of the order is what a client reads instead: `paid` and
 * `orderStatusCode` are re-read off the row every time, and `GET
 * /front/account/orders/{id}` has the rest.
 */
final readonly class CheckoutPlacementOutput
{
    /**
     * @param string $orderStatusCode one of the OrderStatus::CODE_* values, read back after the payment
     *                                module was called
     * @param bool   $alreadyPlaced   true when this cart already had an order and that order is what came
     *                                back: a retried request, a double click, a client that lost the
     *                                answer to the first call. The payment action of such an answer is
     *                                always `none`
     */
    public function __construct(
        public int $orderId,
        public string $orderReference,
        public string $orderStatusCode,
        public bool $paid,
        public bool $alreadyPlaced,
        public CheckoutPaymentActionOutput $paymentAction,
    ) {
    }

    public static function fromResult(CheckoutPlacementResult $result): self
    {
        return new self(
            $result->orderId,
            $result->orderReference,
            $result->orderStatusCode,
            $result->paid,
            $result->alreadyPlaced,
            CheckoutPaymentActionOutput::fromPaymentAction($result->paymentAction),
        );
    }

    /**
     * @return array{orderId: int, orderReference: string, orderStatusCode: string, paid: bool, alreadyPlaced: bool, paymentAction: array{type: string, url: string|null, html: string|null}}
     */
    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'orderReference' => $this->orderReference,
            'orderStatusCode' => $this->orderStatusCode,
            'paid' => $this->paid,
            'alreadyPlaced' => $this->alreadyPlaced,
            'paymentAction' => $this->paymentAction->toArray(),
        ];
    }
}
