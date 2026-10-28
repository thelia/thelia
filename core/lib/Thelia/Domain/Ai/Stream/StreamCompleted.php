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
use Thelia\Domain\Ai\AiResponse;

/**
 * The last event: the whole response, as complete() would have returned it, with tokens, cost and the
 * identifier of the log row already written.
 */
#[Exclude]
final readonly class StreamCompleted implements StreamEvent
{
    public function __construct(public AiResponse $response)
    {
    }
}
