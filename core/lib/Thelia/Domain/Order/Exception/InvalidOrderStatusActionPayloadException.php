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

namespace Thelia\Domain\Order\Exception;

/**
 * The parameters saved for an order status action do not match what the action expects.
 */
final class InvalidOrderStatusActionPayloadException extends \InvalidArgumentException
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function unexpectedFields(string $actionType, array $payload, array $allowedFields): self
    {
        return new self(\sprintf(
            'Action "%s" does not accept the field(s) %s.',
            $actionType,
            implode(', ', array_map(static fn (string $field): string => '"'.$field.'"', array_diff(array_keys($payload), $allowedFields))),
        ));
    }

    public static function missingField(string $actionType, string $field): self
    {
        return new self(\sprintf('Action "%s" needs the field "%s".', $actionType, $field));
    }

    public static function invalidValue(string $actionType, string $field, string $reason): self
    {
        return new self(\sprintf('Action "%s": field "%s" %s.', $actionType, $field, $reason));
    }
}
