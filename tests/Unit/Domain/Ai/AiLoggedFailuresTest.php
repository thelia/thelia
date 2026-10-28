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
use Thelia\Domain\Ai\AiExternalCost;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;

final class AiLoggedFailuresTest extends TestCase
{
    public function testAProviderFailureAfterAPaidCallPointsAtItsLogRow(): void
    {
        $failure = new AiProviderUnavailableException('mistral-main', true, logId: 9005);

        self::assertSame(9005, $failure->logId);
        self::assertNull((new AiProviderUnavailableException('mistral-main', false))->logId);
    }

    public function testABlockedAnswerPointsAtItsLogRow(): void
    {
        $verdict = GuardrailVerdict::fail('mistral_moderation', GuardrailStage::Output, 'hate');

        self::assertSame(9006, (new AiGuardrailBlockedException($verdict, 9006))->logId);
        self::assertNull((new AiGuardrailBlockedException($verdict))->logId);
    }

    public function testTheProviderOfAnExternalCostFitsTheColumnOfTheLog(): void
    {
        $cost = new AiExternalCost('pricewatch.offer_judgement', str_repeat('a', 32), 'jev-latest', '0.00009870');

        self::assertSame(32, \strlen($cost->provider));
    }
}
