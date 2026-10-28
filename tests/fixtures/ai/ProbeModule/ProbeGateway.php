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

namespace AiProbeModule;

use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiCapability;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\AiTransparency;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;

/**
 * The usages the tests of the gateway run through: an administrator tool that generates content, a public
 * assistant that streams, a job with nobody behind it, and an administrator stream.
 */
#[AsAiUsage(id: 'aiprobe.pages', purpose: 'Writes pages.', audience: AiAudience::Admin, tier: AiTier::Fast, capabilities: [AiCapability::StructuredOutput, AiCapability::Tools], transparency: AiTransparency::Generative, maxInputCharacters: 1000)]
#[AsAiUsage(id: 'aiprobe.assistant', purpose: 'Answers the visitors.', audience: AiAudience::Public, tier: AiTier::Fast, capabilities: [AiCapability::Tools, AiCapability::Streaming], transparency: AiTransparency::Interactive, guardrails: ['probe_moderation' => GuardrailMode::Block], maxInputCharacters: 500)]
#[AsAiUsage(id: 'aiprobe.scoring', purpose: 'Scores offers.', audience: AiAudience::System, tier: AiTier::Fast)]
#[AsAiUsage(id: 'aiprobe.drafts', purpose: 'Streams drafts.', audience: AiAudience::Admin, tier: AiTier::Fast, capabilities: [AiCapability::Streaming], transparency: AiTransparency::Generative)]
final class ProbeGateway
{
}
