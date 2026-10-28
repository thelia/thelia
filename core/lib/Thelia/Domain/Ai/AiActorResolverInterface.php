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

namespace Thelia\Domain\Ai;

/**
 * Finds who a call is made for, without assuming an HTTP request exists: the logged-in administrator (unless the usage
 * is for the public or for customers), then the logged-in customer, then the visitor by session or IP address; in a command or a worker, the system.
 */
interface AiActorResolverInterface
{
    /**
     * @param AiAudience|null $audience the audience of the usage the call serves: a `public` or `customer` usage never
     *                                  resolves an administrator, whoever is signed in (a chat of the shop is not an
     *                                  action of the back office); without it, the administrator comes first
     */
    public function resolve(?AiAudience $audience = null): AiActor;
}
