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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * Tokens of one call, the same meaning whatever the provider: input tokens billed at the full rate, the
 * cached ones counted apart.
 */
#[Exclude]
final readonly class AiTokenUsage
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
    ) {
        AiAssert::notNegative($inputTokens, 'Input tokens');
        AiAssert::notNegative($outputTokens, 'Output tokens');
        AiAssert::notNegative($cacheReadTokens, 'Cache read tokens');
        AiAssert::notNegative($cacheWriteTokens, 'Cache write tokens');
    }

    public function total(): int
    {
        return $this->inputTokens + $this->outputTokens + $this->cacheReadTokens + $this->cacheWriteTokens;
    }
}
