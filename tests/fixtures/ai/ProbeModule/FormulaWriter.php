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
 * A usage whose purpose a spreadsheet would read as a formula.
 */
#[AsAiUsage(id: 'aiprobe.formula', purpose: '=HYPERLINK("http://example.test","click")', audience: AiAudience::Admin, tier: AiTier::Fast)]
#[AsAiUsage(id: 'aiprobe.undeclared', purpose: 'Answers visitors, declares nothing.', audience: AiAudience::Public, tier: AiTier::Fast)]
final class FormulaWriter
{
}
