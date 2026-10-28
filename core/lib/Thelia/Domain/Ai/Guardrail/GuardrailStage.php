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

enum GuardrailStage: string
{
    /** Before the call: the request. */
    case Input = 'input';
    /** After the call: what the model produced. */
    case Output = 'output';
}
