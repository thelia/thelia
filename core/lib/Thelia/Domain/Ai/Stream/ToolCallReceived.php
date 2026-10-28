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

namespace Thelia\Domain\Ai\Stream;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiToolCall;

/**
 * A complete tool call: its arguments are whole, the module may run the tool.
 */
#[Exclude]
final readonly class ToolCallReceived implements StreamEvent
{
    public function __construct(public AiToolCall $toolCall)
    {
    }
}
