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

namespace Thelia\Module;

/**
 * A payment module that moves the order status itself, from its own configuration,
 * rather than leaving it to the payment journal.
 *
 * Optional: by default the core moves the order along with the journal — on hold for
 * capture once an authorization succeeds, paid once nothing is held and something was
 * taken, back to not paid once everything was released. A module answering true here
 * keeps writing its journal lines, and the core leaves the status of its orders alone.
 */
interface PaymentModuleManagingOrderStatusInterface extends PaymentModuleInterface
{
    public function managesOrderStatus(): bool;
}
