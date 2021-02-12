<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core;

/**
 * Class TheliaKernelEvents.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
final class TheliaKernelEvents
{
    public const SESSION = 'thelia_kernel.session';

    // -- Kernel Error Message Handle ---------------------------

    public const THELIA_HANDLE_ERROR = 'thelia_kernel.handle_error';
}
