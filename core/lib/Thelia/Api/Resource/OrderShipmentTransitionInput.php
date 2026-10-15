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

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The body of a shipment transition: ship the draft, with a tracking number typed at
 * the last moment when the label was printed since, or cancel it.
 */
class OrderShipmentTransitionInput
{
    public const ACTION_SHIP = 'ship';
    public const ACTION_CANCEL = 'cancel';

    #[NotBlank(groups: [OrderShipment::GROUP_ADMIN_TRANSITION])]
    #[Choice(choices: [self::ACTION_SHIP, self::ACTION_CANCEL], groups: [OrderShipment::GROUP_ADMIN_TRANSITION])]
    #[Groups([OrderShipment::GROUP_ADMIN_TRANSITION])]
    public ?string $action = null;

    #[Groups([OrderShipment::GROUP_ADMIN_TRANSITION])]
    public ?string $deliveryRef = null;
}
