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
use Thelia\Domain\Ai\AsAiUsage;

/**
 * One usage per audience, for the tests of the tool registry.
 */
#[AsAiUsage(id: 'aiprobe.tools_admin', purpose: 'Assists an administrator.', audience: AiAudience::Admin, tier: AiTier::Fast, capabilities: [AiCapability::Tools])]
#[AsAiUsage(id: 'aiprobe.tools_customer', purpose: 'Assists a customer.', audience: AiAudience::Customer, tier: AiTier::Fast, capabilities: [AiCapability::Tools])]
#[AsAiUsage(id: 'aiprobe.tools_public', purpose: 'Assists a visitor.', audience: AiAudience::Public, tier: AiTier::Fast, capabilities: [AiCapability::Tools])]
#[AsAiUsage(id: 'aiprobe.tools_system', purpose: 'Runs a job.', audience: AiAudience::System, tier: AiTier::Fast)]
final class ProbeToolUsages
{
}
