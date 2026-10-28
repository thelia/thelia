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

enum AiToolCallStatus: string
{
    case Succeeded = 'succeeded';
    case PendingApproval = 'pending_approval';
    case InvalidArguments = 'invalid_arguments';
    case UnknownTool = 'unknown_tool';
    /** Logged apart from unknown_tool, but the model and the MCP client read the same answer. */
    case Denied = 'denied';
    case Limited = 'limited';
    case ResultTooLarge = 'result_too_large';
    case Failed = 'failed';
}
