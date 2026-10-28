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

enum AiActorType: string
{
    case Admin = 'admin';
    case Customer = 'customer';
    /** An anonymous visitor, known by its session or, without a session, by its IP address. */
    case Visitor = 'visitor';
    /** A command, a job or a scheduled task. */
    case System = 'system';
}
