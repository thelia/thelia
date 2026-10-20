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
 * A payment module whose provider takes a single capture per authorization: validating it
 * sets the amount taken, and the provider lets the rest go with it.
 *
 * Optional: a module that captures in several steps leaves it aside. Once a capture is
 * asked, pending or taken, the core no longer offers another one on the order (the
 * back office, the admin API and the capture service read nothing left to capture). While
 * that capture waits for its answer, no administrator cancels the order; once it is taken,
 * cancelling no longer needs the capture right. The release the core still asks then
 * (voidAuthorization()) must only let the rest go, never cancel the capture.
 */
interface PaymentModuleCapturingOnceInterface extends PaymentModuleWithCaptureInterface
{
    /**
     * Whether the provider takes a single capture per authorization: a property of the provider,
     * not a setting, since it is read for the orders already authorized too. Reads no provider
     * and throws nothing.
     */
    public function capturesOnce(): bool;
}
