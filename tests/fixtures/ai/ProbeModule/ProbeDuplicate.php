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
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\AsAiUsage;

/**
 * Declares an identifier ProbeWriter already declared.
 */
#[AsAiUsage(id: 'aiprobe.writing', purpose: 'Writes the same thing again.', audience: AiAudience::System, tier: AiTier::Fast)]
final class ProbeDuplicate
{
}
