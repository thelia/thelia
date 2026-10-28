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
use Thelia\Domain\Ai\AiTransparency;
use Thelia\Domain\Ai\AsAiUsage;

/**
 * A service of a module that declares two usages, as the service of a real module would.
 */
#[AsAiUsage(id: 'aiprobe.writing', purpose: 'Writes probe texts.', audience: AiAudience::Admin, tier: AiTier::Fast, transparency: AiTransparency::Generative)]
#[AsAiUsage(id: 'aiprobe.chat', purpose: 'Chats with visitors.', audience: AiAudience::Public, tier: AiTier::Fast, transparency: AiTransparency::Interactive)]
final class ProbeWriter
{
}
