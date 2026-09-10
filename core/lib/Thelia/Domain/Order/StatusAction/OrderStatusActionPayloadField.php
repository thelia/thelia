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

namespace Thelia\Domain\Order\StatusAction;

/**
 * One parameter of an order status action, as the back office renders it.
 */
final readonly class OrderStatusActionPayloadField
{
    public const TYPE_TEXT = 'text';
    public const TYPE_CHOICE = 'choice';

    /**
     * @param array<string, string>|null $choices value => label, for a choice field
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = self::TYPE_TEXT,
        public bool $required = true,
        public ?array $choices = null,
    ) {
    }

    /**
     * @param array<string, string> $choices value => label
     */
    public static function choice(string $name, string $label, array $choices, bool $required = true): self
    {
        return new self($name, $label, self::TYPE_CHOICE, $required, $choices);
    }
}
