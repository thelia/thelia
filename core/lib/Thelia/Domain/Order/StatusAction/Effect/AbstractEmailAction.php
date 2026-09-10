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

namespace Thelia\Domain\Order\StatusAction\Effect;

use Thelia\Domain\Order\Exception\InvalidOrderStatusActionPayloadException;
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\Order\StatusAction\OrderStatusActionInterface;
use Thelia\Domain\Order\StatusAction\OrderStatusActionPayloadField;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\MessageQuery;

/**
 * Sends one of the shop's message templates when an order changes status.
 *
 * Only an existing message code is accepted, never a free body: the action must
 * not turn the shop into a mail relay.
 */
abstract readonly class AbstractEmailAction implements OrderStatusActionInterface
{
    public const FIELD_MESSAGE_CODE = 'message_code';

    public function __construct(
        protected readonly MailerFactory $mailer,
    ) {
    }

    public function describePayload(): array
    {
        $choices = [];

        foreach (MessageQuery::create()->orderByName()->find() as $message) {
            $choices[$message->getName()] = $message->getName();
        }

        return [OrderStatusActionPayloadField::choice(self::FIELD_MESSAGE_CODE, 'Message', $choices)];
    }

    public function normalizePayload(array $payload): array
    {
        if ([] !== array_diff(array_keys($payload), [self::FIELD_MESSAGE_CODE])) {
            throw InvalidOrderStatusActionPayloadException::unexpectedFields(static::getType(), $payload, [self::FIELD_MESSAGE_CODE]);
        }

        $messageCode = $payload[self::FIELD_MESSAGE_CODE] ?? null;

        if (!\is_string($messageCode) || '' === $messageCode) {
            throw InvalidOrderStatusActionPayloadException::missingField(static::getType(), self::FIELD_MESSAGE_CODE);
        }

        if (null === MessageQuery::create()->findOneByName($messageCode)) {
            throw InvalidOrderStatusActionPayloadException::invalidValue(static::getType(), self::FIELD_MESSAGE_CODE, \sprintf('names no message: "%s"', $messageCode));
        }

        return [self::FIELD_MESSAGE_CODE => $messageCode];
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageParameters(OrderStatusActionContext $context): array
    {
        return [
            'order_id' => $context->order->getId(),
            'order_ref' => $context->order->getRef(),
            'order_status_code' => $context->newStatus->getCode(),
            'previous_order_status_code' => $context->previousStatus?->getCode(),
        ];
    }
}
