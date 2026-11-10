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

namespace Thelia\Model;

use Thelia\Model\Base\SubscriptionOrder as BaseSubscriptionOrder;

/**
 * One installment of a subscription and the order it is charged with: one row, and one
 * order at most, per installment.
 */
class SubscriptionOrder extends BaseSubscriptionOrder
{
}
