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
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The body of an admin return transition: the target status code and, for a
 * refusal, the motive shown to the customer.
 */
class OrderReturnTransitionInput
{
    #[NotBlank(groups: [OrderReturn::GROUP_ADMIN_TRANSITION])]
    #[Groups([OrderReturn::GROUP_ADMIN_TRANSITION])]
    public ?string $statusCode = null;

    #[Groups([OrderReturn::GROUP_ADMIN_TRANSITION])]
    public ?string $refusalReason = null;
}
