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

namespace Thelia\Domain\Ai\Tool\Exception;

/**
 * Why an approval could not be decided: the `reason` of AiToolApprovalException.
 */
enum AiToolApprovalFailure: string
{
    case NotFound = 'not_found';
    case NotPending = 'not_pending';
    case Expired = 'expired';
    case Forbidden = 'forbidden';
    /** The tool is gone, is no longer a write tool, or asks for another right than the one stored on the row. */
    case ToolGone = 'tool_gone';
    case NoAdminSession = 'no_admin_session';
    /** A decision was asked for while a tool call was running. */
    case Reentrant = 'reentrant';
}
