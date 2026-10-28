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
 * Why the model stopped, the same value whatever the provider.
 */
enum AiFinishReason: string
{
    /** The model finished its answer. */
    case Completed = 'completed';
    /** The output limit cut the answer: the text or the structured data may be incomplete. */
    case MaxTokens = 'max_tokens';
    /** The model waits for the results of the tools it called: the module runs them and asks again. */
    case ToolCalls = 'tool_calls';
    /** The provider refused to answer. */
    case ContentFilter = 'content_filter';
    /** Any other reason a provider gives (`pause_turn`, a new word): the layer never fails on a reason it does not know. */
    case Other = 'other';

    public static function fromProvider(?string $reason): self
    {
        return null === $reason ? self::Other : (self::tryFrom($reason) ?? self::Other);
    }
}
