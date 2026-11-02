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

namespace Thelia\Tests\Unit\Domain\Ai;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiAssert;

final class AiAssertTest extends TestCase
{
    public function testAToolNameThatEndsOnALineBreakIsRefused(): void
    {
        self::assertSame('search_products', (new AiToolCall('call_1', 'search_products', []))->name);

        $this->expectException(InvalidAiArgumentException::class);

        new AiToolCall('call_1', "search_products\n", []);
    }

    public function testAGuardrailNameThatEndsOnALineBreakIsRefused(): void
    {
        AiAssert::token('probe_moderation', 'A guardrail name');

        $this->expectException(InvalidAiArgumentException::class);

        AiAssert::token("probe_moderation\n", 'A guardrail name');
    }
}
