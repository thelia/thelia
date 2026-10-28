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

enum AiMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    /** The result of a tool the module ran for a tool call of the assistant. */
    case Tool = 'tool';
}
