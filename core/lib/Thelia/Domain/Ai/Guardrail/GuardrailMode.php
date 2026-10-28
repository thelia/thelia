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

namespace Thelia\Domain\Ai\Guardrail;

enum GuardrailMode: string
{
    /** A failed check stops the call: AiGuardrailBlockedException. */
    case Block = 'block';
    /** A failed check is written to the log and returned in the response; the call goes on. */
    case Annotate = 'annotate';
}
