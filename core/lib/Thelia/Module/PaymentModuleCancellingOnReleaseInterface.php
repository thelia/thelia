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
 * A payment module that cancels the order itself when its provider reports the authorization
 * ended without anything taken, cancelled or lapsed.
 *
 * Optional: by default, once everything an authorization held was released and nothing was
 * taken, the core moves the order on hold back to not paid. A module answering true here
 * keeps writing the release to the journal first, then cancels the order: the core leaves
 * that step to it, so the order goes to cancelled once, not through not paid.
 */
interface PaymentModuleCancellingOnReleaseInterface extends PaymentModuleInterface
{
    public function cancelsOrderOnRelease(): bool;
}
