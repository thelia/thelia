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

namespace Thelia\Domain\Ai\Log;

/**
 * The `kind` column of the usage log.
 *
 * @internal
 */
enum AiLogKind: string
{
    case Completion = 'completion';
    case Stream = 'stream';
    /** A cost a module paid outside the gateway (AiCostRecorderInterface). */
    case External = 'external';
}
