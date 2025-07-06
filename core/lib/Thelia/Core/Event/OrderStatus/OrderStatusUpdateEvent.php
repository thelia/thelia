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

namespace Thelia\Core\Event\OrderStatus;

/**
 * Class OrderStatusUpdateEvent.
 *
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class OrderStatusUpdateEvent extends OrderStatusEvent
{
    /**
     * OrderStatusUpdateEvent constructor.
     *
     * @param int $id
     */
    public function __construct(protected $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }
}
