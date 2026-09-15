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

namespace Thelia\Domain\Order\DTO;

use Thelia\Domain\Order\Enum\OrderHistoryActorType;

/**
 * Who is behind one order history entry, resolved once and frozen.
 *
 * The label is a snapshot, not a link: an admin account or a module may be gone long
 * before anyone reads the history back, and the entry still has to say who acted.
 * The admin id is kept alongside it only so a still-existing admin can be linked to.
 */
final readonly class OrderHistoryActor
{
    public function __construct(
        public OrderHistoryActorType $actorType,
        public ?string $label = null,
        public ?int $adminId = null,
    ) {
    }

    public static function system(): self
    {
        return new self(OrderHistoryActorType::SYSTEM);
    }
}
