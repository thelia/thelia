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

namespace Thelia\Domain\Ai\Tool;

/**
 * Where a tool call comes from.
 */
enum AiToolChannel: string
{
    /** An internal agent, through the AI gateway. */
    case Gateway = 'gateway';
    /** An external client, through the MCP server of the core. */
    case Mcp = 'mcp';
    /** The back office: an administrator amends a proposal and the tool replays propose() for him. */
    case BackOffice = 'back_office';
}
