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
 * back office, the admin API and the capture service read nothing left to capture), and
 * cancelling the order no longer needs the capture right: there is nothing more to release.
 */
interface PaymentModuleCapturingOnceInterface extends PaymentModuleWithCaptureInterface
{
    /**
     * Whether, as currently configured, the provider takes a single capture per authorization.
     */
    public function capturesOnce(): bool;
}
